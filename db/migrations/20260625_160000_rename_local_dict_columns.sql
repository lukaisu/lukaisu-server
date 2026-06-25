-- Rename the local_dictionaries and local_dictionary_entries columns from the
-- legacy Ld*/Le* prefixes to plain snake_case. Last table of the column-naming
-- modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if
-- both exist; no-op when neither exists. Indexes follow the column rename
-- automatically.
--
-- These two tables were never carried in baseline.sql before this campaign and
-- the original 20251223_173100_add_local_dictionaries migration declared
-- LdLgID as INT(11) while languages(LgID/id) is TINYINT(3) UNSIGNED, so its
-- foreign key failed (errno 150) and the tables were not created on fresh
-- installs at all. baseline.sql now owns them with the correct snake_case
-- columns and types; this migration upgrades existing/demo databases (where the
-- tables exist with the old Ld*/Le* names and no foreign keys).
--
-- Foreign keys are dropped before the renames and recreated against the renamed
-- columns afterwards. The DROPs use IF EXISTS and the ADDs are guarded, so the
-- migration is safely re-runnable on every install path:
--   fk_local_dict_language  local_dictionaries(language_id)        -> languages(id)
--   fk_local_dict_user      local_dictionaries(user_id)            -> users(id)
--   fk_entry_dictionary     local_dictionary_entries(local_dictionary_id)
--                                                              -> local_dictionaries(id)
SET @db = DATABASE();

ALTER TABLE local_dictionary_entries DROP FOREIGN KEY IF EXISTS fk_entry_dictionary;
ALTER TABLE local_dictionaries DROP FOREIGN KEY IF EXISTS fk_local_dict_language;
ALTER TABLE local_dictionaries DROP FOREIGN KEY IF EXISTS fk_local_dict_user;

-- LdID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdID id int(10) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdLgID language_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdName -> name
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdName');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'name');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdName name varchar(100) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdName', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdDescription -> description
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdDescription');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'description');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdDescription description varchar(500) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdDescription', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdSourceFormat -> source_format
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdSourceFormat');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'source_format');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdSourceFormat source_format varchar(20) NOT NULL DEFAULT ''csv''', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdSourceFormat', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdEntryCount -> entry_count
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdEntryCount');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'entry_count');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdEntryCount entry_count int(10) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdEntryCount', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdPriority -> priority
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdPriority');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'priority');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdPriority priority tinyint(3) unsigned NOT NULL DEFAULT 1', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdPriority', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdEnabled -> enabled
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdEnabled');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'enabled');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdEnabled enabled tinyint(1) unsigned NOT NULL DEFAULT 1', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdEnabled', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdCreated -> created_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdCreated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'created_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdCreated created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdCreated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LdUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'LdUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionaries' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionaries CHANGE COLUMN LdUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionaries DROP COLUMN LdUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeID id bigint(20) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeLdID -> local_dictionary_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeLdID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'local_dictionary_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeLdID local_dictionary_id int(10) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeLdID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeTerm -> term
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeTerm');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'term');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeTerm term varchar(250) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeTerm', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeTermLc -> term_lc
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeTermLc');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'term_lc');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeTermLc term_lc varchar(250) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeTermLc', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeDefinition -> definition
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeDefinition');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'definition');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeDefinition definition text NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeDefinition', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LeReading -> reading
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LeReading');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'reading');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LeReading reading varchar(250) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LeReading', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LePartOfSpeech -> part_of_speech
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'LePartOfSpeech');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND column_name = 'part_of_speech');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE local_dictionary_entries CHANGE COLUMN LePartOfSpeech part_of_speech varchar(50) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE local_dictionary_entries DROP COLUMN LePartOfSpeech', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the foreign keys against the renamed columns (guarded so re-runs and
-- fresh installs, where baseline.sql already added them, stay no-ops).
SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'local_dictionaries' AND constraint_name = 'fk_local_dict_language');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE local_dictionaries ADD CONSTRAINT fk_local_dict_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'local_dictionaries' AND constraint_name = 'fk_local_dict_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE local_dictionaries ADD CONSTRAINT fk_local_dict_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'local_dictionary_entries' AND constraint_name = 'fk_entry_dictionary');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE local_dictionary_entries ADD CONSTRAINT fk_entry_dictionary FOREIGN KEY (local_dictionary_id) REFERENCES local_dictionaries(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

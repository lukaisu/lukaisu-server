-- Rename words (`Wo*`) and temp_words columns to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist (fresh installs where a later ADD COLUMN IF NOT EXISTS re-added WoLemma /
-- WoLemmaLC / WoNotes). Indexes follow the column rename automatically.
-- Score columns (today_score/tomorrow_score/random) are renamed for consistency;
-- the FSRS work (issue #238) will later drop them.

-- Drop the words FKs that name WoLgID / WoUsID, recreated against new names below.
ALTER TABLE words DROP FOREIGN KEY IF EXISTS fk_words_language;
ALTER TABLE words DROP FOREIGN KEY IF EXISTS fk_words_user;

SET @db = DATABASE();

-- WoID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoID id int(11) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoLgID language_id int(11) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoText text varchar(250) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTextLC -> text_lc
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoTextLC');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'text_lc');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoTextLC text_lc varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoTextLC', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoLemma -> lemma
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoLemma');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'lemma');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoLemma lemma varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_uca1400_ai_ci DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoLemma', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoLemmaLC -> lemma_lc
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoLemmaLC');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'lemma_lc');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoLemmaLC lemma_lc varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_uca1400_ai_ci DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoLemmaLC', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoStatus -> status
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoStatus');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'status');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoStatus status tinyint(4) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoStatus', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTranslation -> translation
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoTranslation');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'translation');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoTranslation translation varchar(500) NOT NULL DEFAULT ''*''', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoTranslation', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoRomanization -> romanization
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoRomanization');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'romanization');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoRomanization romanization varchar(100) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoRomanization', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoSentence -> sentence
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoSentence');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'sentence');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoSentence sentence varchar(1000) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoSentence', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoNotes -> notes
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoNotes');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'notes');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoNotes notes varchar(1000) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoNotes', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoWordCount -> word_count
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoWordCount');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'word_count');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoWordCount word_count tinyint(3) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoWordCount', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoCreated -> created_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoCreated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'created_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoCreated created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoCreated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoStatusChanged -> status_changed_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoStatusChanged');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'status_changed_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoStatusChanged status_changed_at timestamp NOT NULL DEFAULT ''1970-01-01 12:00:00''', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoStatusChanged', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTodayScore -> today_score
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoTodayScore');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'today_score');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoTodayScore today_score double NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoTodayScore', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTomorrowScore -> tomorrow_score
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoTomorrowScore');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'tomorrow_score');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoTomorrowScore tomorrow_score double NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoTomorrowScore', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoRandom -> random
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'WoRandom');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'words' AND column_name = 'random');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE words CHANGE COLUMN WoRandom random double NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE words DROP COLUMN WoRandom', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- WoText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoText text varchar(250) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTextLC -> text_lc
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoTextLC');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'text_lc');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoTextLC text_lc varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoTextLC', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTranslation -> translation
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoTranslation');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'translation');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoTranslation translation varchar(500) NOT NULL DEFAULT ''*''', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoTranslation', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoRomanization -> romanization
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoRomanization');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'romanization');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoRomanization romanization varchar(100) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoRomanization', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoSentence -> sentence
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoSentence');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'sentence');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoSentence sentence varchar(1000) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoSentence', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WoTaglist -> tag_list
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'WoTaglist');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_words' AND column_name = 'tag_list');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_words CHANGE COLUMN WoTaglist tag_list varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_words DROP COLUMN WoTaglist', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the words FKs against the new column names.
ALTER TABLE words ADD CONSTRAINT fk_words_language FOREIGN KEY (language_id) REFERENCES languages(LgID) ON DELETE CASCADE;
ALTER TABLE words ADD CONSTRAINT fk_words_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;

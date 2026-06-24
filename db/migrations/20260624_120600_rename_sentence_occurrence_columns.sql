-- Rename sentences / word_occurrences / temp_word_occurrences columns from the
-- legacy Se*, Ti2* and Ti* prefixes to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist. Indexes follow the column rename automatically.
--
-- Reserved words: *Order -> position. TiCount (a running character offset) -> char_position.
--
-- FKs touched (dropped before the renames, recreated against the new names after):
--   sentences:        fk_sentences_language (SeLgID), fk_sentences_text (SeTxID)
--   word_occurrences: fk_word_occurrences_sentence (Ti2SeID -> sentences.SeID),
--                     fk_word_occurrences_text (Ti2TxID), fk_word_occurrences_word (Ti2WoID)
-- The sentence FK spans a parent-side rename (sentences.SeID -> id) and a child-side
-- rename (word_occurrences.Ti2SeID -> sentence_id); both happen in this migration.

ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_sentence;
ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_text;
ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_word;
ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_language;
ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_text;

-- ===== sentences =====
SET @db = DATABASE();

-- SeID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeID id mediumint(8) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SeLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeLgID language_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SeTxID -> text_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeTxID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'text_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeTxID text_id smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeTxID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SeOrder -> position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeOrder');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeOrder position smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeOrder', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SeText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeText text text DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- SeFirstPos -> first_pos
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'SeFirstPos');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'sentences' AND column_name = 'first_pos');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE sentences CHANGE COLUMN SeFirstPos first_pos smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE sentences DROP COLUMN SeFirstPos', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===== word_occurrences =====

-- Ti2WoID -> word_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2WoID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'word_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2WoID word_id mediumint(8) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2WoID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2LgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2LgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2LgID language_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2LgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2TxID -> text_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2TxID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'text_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2TxID text_id smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2TxID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2SeID -> sentence_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2SeID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'sentence_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2SeID sentence_id mediumint(8) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2SeID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2Order -> position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2Order');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2Order position smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2Order', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2WordCount -> word_count
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2WordCount');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'word_count');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2WordCount word_count tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2WordCount', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ti2Text -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'Ti2Text');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_occurrences' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_occurrences CHANGE COLUMN Ti2Text text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_occurrences DROP COLUMN Ti2Text', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===== temp_word_occurrences =====

-- TiCount -> char_position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'TiCount');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'char_position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_word_occurrences CHANGE COLUMN TiCount char_position smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_word_occurrences DROP COLUMN TiCount', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TiSeID -> sentence_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'TiSeID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'sentence_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_word_occurrences CHANGE COLUMN TiSeID sentence_id mediumint(8) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_word_occurrences DROP COLUMN TiSeID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TiOrder -> position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'TiOrder');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_word_occurrences CHANGE COLUMN TiOrder position smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_word_occurrences DROP COLUMN TiOrder', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TiWordCount -> word_count
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'TiWordCount');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'word_count');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_word_occurrences CHANGE COLUMN TiWordCount word_count tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_word_occurrences DROP COLUMN TiWordCount', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TiText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'TiText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'temp_word_occurrences' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE temp_word_occurrences CHANGE COLUMN TiText text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE temp_word_occurrences DROP COLUMN TiText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Recreate the FKs against the new column / parent-column names.
ALTER TABLE sentences ADD CONSTRAINT fk_sentences_language FOREIGN KEY (language_id) REFERENCES languages(LgID) ON DELETE CASCADE;
ALTER TABLE sentences ADD CONSTRAINT fk_sentences_text FOREIGN KEY (text_id) REFERENCES texts(TxID) ON DELETE CASCADE;
ALTER TABLE word_occurrences ADD CONSTRAINT fk_word_occurrences_sentence FOREIGN KEY (sentence_id) REFERENCES sentences(id) ON DELETE CASCADE;
ALTER TABLE word_occurrences ADD CONSTRAINT fk_word_occurrences_text FOREIGN KEY (text_id) REFERENCES texts(TxID) ON DELETE CASCADE;
ALTER TABLE word_occurrences ADD CONSTRAINT fk_word_occurrences_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL;

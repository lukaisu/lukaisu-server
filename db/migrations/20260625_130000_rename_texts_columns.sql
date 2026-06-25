-- Rename the texts table columns from the legacy Tx* prefix to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist; no-op when neither exists. Indexes follow the column rename automatically.
--
-- Reserved words: TxPosition -> position (verified usable unquoted on MariaDB 12.1).
--
-- The book columns (TxBkID/TxChapterNum/TxChapterTitle) only exist on installs that
-- ran the books-feature migrations; the guarded blocks no-op where they are absent,
-- and the fk_texts_book recreation is guarded on the column + books table existing.
--
-- FKs touched (dropped before the renames, recreated against the new names after):
--   on texts:      fk_texts_language (TxLgID -> languages.LgID),
--                  fk_texts_user (TxUsID -> users.UsID),
--                  fk_texts_book (TxBkID -> books.id, books-feature installs only)
--   parent-side (texts.TxID -> id), so the children referencing it are dropped/re-added:
--                  sentences.fk_sentences_text, word_occurrences.fk_word_occurrences_text,
--                  text_tag_map.fk_text_tag_map_text
ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_text;
ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_text;
ALTER TABLE text_tag_map DROP FOREIGN KEY IF EXISTS fk_text_tag_map_text;
ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_language;
ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_user;
ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_book;

SET @db = DATABASE();

-- TxID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxID id smallint(5) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxBkID -> book_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxBkID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'book_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxBkID book_id smallint(5) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxBkID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxChapterNum -> chapter_num
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxChapterNum');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'chapter_num');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxChapterNum chapter_num smallint(5) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxChapterNum', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxChapterTitle -> chapter_title
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxChapterTitle');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'chapter_title');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxChapterTitle chapter_title varchar(200) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxChapterTitle', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxLgID language_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxTitle -> title
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxTitle');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'title');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxTitle title varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxTitle', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxText text text NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxAnnotatedText -> annotated_text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxAnnotatedText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'annotated_text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxAnnotatedText annotated_text longtext NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxAnnotatedText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxAudioURI -> audio_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxAudioURI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'audio_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxAudioURI audio_uri varchar(2048) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxAudioURI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxSourceURI -> source_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxSourceURI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'source_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxSourceURI source_uri varchar(1000) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxSourceURI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxPosition -> position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxPosition');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxPosition position smallint(5) DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxPosition', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxAudioPosition -> audio_position
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxAudioPosition');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'audio_position');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxAudioPosition audio_position float DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxAudioPosition', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TxArchivedAt -> archived_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxArchivedAt');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'archived_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE texts CHANGE COLUMN TxArchivedAt archived_at datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE texts DROP COLUMN TxArchivedAt', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the FKs against the new column / parent-column names.
ALTER TABLE texts ADD CONSTRAINT fk_texts_language FOREIGN KEY (language_id) REFERENCES languages(LgID) ON DELETE CASCADE;
ALTER TABLE texts ADD CONSTRAINT fk_texts_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;
ALTER TABLE sentences ADD CONSTRAINT fk_sentences_text FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;
ALTER TABLE word_occurrences ADD CONSTRAINT fk_word_occurrences_text FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;
ALTER TABLE text_tag_map ADD CONSTRAINT fk_text_tag_map_text FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

-- fk_texts_book only exists on books-feature installs; recreate it only when the
-- renamed column and the books table are both present.
SET @has_book_col = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'book_id');
SET @has_books_tbl = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'books');
SET @has_book_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'texts' AND constraint_name = 'fk_texts_book');
SET @sql = IF(@has_book_col > 0 AND @has_books_tbl > 0 AND @has_book_fk = 0, 'ALTER TABLE texts ADD CONSTRAINT fk_texts_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

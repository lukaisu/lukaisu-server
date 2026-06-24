-- Rename books columns from the legacy `Bk*` prefix to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist. Indexes follow the column rename automatically. The BkLgID/LgID type
-- mismatch the original migration worried about is already resolved (both int(11)).
--
-- FKs touched: fk_books_language (BkLgID), fk_books_user (BkUsID), and the inbound
-- fk_texts_book (texts.TxBkID -> books.BkID). All are dropped before the rename and
-- recreated against the new names afterwards.

ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_book;
ALTER TABLE books DROP FOREIGN KEY IF EXISTS fk_books_language;
ALTER TABLE books DROP FOREIGN KEY IF EXISTS fk_books_user;

SET @db = DATABASE();

-- BkID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkID id smallint(5) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkLgID language_id int(11) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkTitle -> title
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkTitle');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'title');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkTitle title varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkTitle', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkAuthor -> author
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkAuthor');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'author');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkAuthor author varchar(200) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkAuthor', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkDescription -> description
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkDescription');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'description');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkDescription description text DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkDescription', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkCoverPath -> cover_path
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkCoverPath');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'cover_path');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkCoverPath cover_path varchar(500) DEFAULT NULL COMMENT ''Path to cover image file''', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkCoverPath', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkSourceType -> source_type
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkSourceType');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'source_type');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkSourceType source_type enum(''text'',''epub'',''pdf'') NOT NULL DEFAULT ''text''', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkSourceType', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkSourceHash -> source_hash
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkSourceHash');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'source_hash');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkSourceHash source_hash varchar(64) DEFAULT NULL COMMENT ''SHA-256 hash for duplicate detection''', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkSourceHash', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkTotalChapters -> total_chapters
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkTotalChapters');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'total_chapters');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkTotalChapters total_chapters smallint(5) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkTotalChapters', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkCurrentChapter -> current_chapter
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkCurrentChapter');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'current_chapter');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkCurrentChapter current_chapter smallint(5) unsigned NOT NULL DEFAULT 1', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkCurrentChapter', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkCreated -> created_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkCreated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'created_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkCreated created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkCreated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- BkUpdated -> updated_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'BkUpdated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'updated_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE books CHANGE COLUMN BkUpdated updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE books DROP COLUMN BkUpdated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the books FKs against the new column names.
ALTER TABLE books ADD CONSTRAINT fk_books_language FOREIGN KEY (language_id) REFERENCES languages(LgID);
ALTER TABLE books ADD CONSTRAINT fk_books_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;

-- Recreate the texts -> books FK (child column TxBkID is renamed later, in the
-- texts stage). Only when the text-side column exists and the FK is absent.
SET @has_txbkid = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'texts' AND column_name = 'TxBkID');
SET @has_fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'texts' AND CONSTRAINT_NAME = 'fk_texts_book');
SET @sql = IF(@has_txbkid > 0 AND @has_fk = 0, 'ALTER TABLE texts ADD CONSTRAINT fk_texts_book FOREIGN KEY (TxBkID) REFERENCES books(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

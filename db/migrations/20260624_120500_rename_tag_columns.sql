-- Rename tags (`Tg*`), word_tag_map (`Wt*`), text_tags (`T2*`) and
-- text_tag_map (`Tt*`) columns to snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent. FKs are dropped before the rename and recreated against the new names.
-- (word_tag_map's word/tag FKs only exist where column types match; they fail
-- harmlessly otherwise, as today.)

ALTER TABLE tags DROP FOREIGN KEY IF EXISTS fk_tags_user;
ALTER TABLE text_tags DROP FOREIGN KEY IF EXISTS fk_text_tags_user;
ALTER TABLE text_tag_map DROP FOREIGN KEY IF EXISTS fk_text_tag_map_tag;
ALTER TABLE text_tag_map DROP FOREIGN KEY IF EXISTS fk_text_tag_map_text;
ALTER TABLE word_tag_map DROP FOREIGN KEY IF EXISTS fk_word_tag_map_word;
ALTER TABLE word_tag_map DROP FOREIGN KEY IF EXISTS fk_word_tag_map_tag;

SET @db = DATABASE();

-- TgID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'TgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE tags CHANGE COLUMN TgID id int(11) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE tags DROP COLUMN TgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TgUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'TgUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE tags CHANGE COLUMN TgUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE tags DROP COLUMN TgUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TgText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'TgText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE tags CHANGE COLUMN TgText text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE tags DROP COLUMN TgText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TgComment -> comment
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'TgComment');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'tags' AND column_name = 'comment');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE tags CHANGE COLUMN TgComment comment varchar(200) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE tags DROP COLUMN TgComment', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- WtWoID -> word_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_tag_map' AND column_name = 'WtWoID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_tag_map' AND column_name = 'word_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_tag_map CHANGE COLUMN WtWoID word_id mediumint(8) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_tag_map DROP COLUMN WtWoID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WtTgID -> tag_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_tag_map' AND column_name = 'WtTgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'word_tag_map' AND column_name = 'tag_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE word_tag_map CHANGE COLUMN WtTgID tag_id smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE word_tag_map DROP COLUMN WtTgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- T2ID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'T2ID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tags CHANGE COLUMN T2ID id smallint(5) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tags DROP COLUMN T2ID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- T2UsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'T2UsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tags CHANGE COLUMN T2UsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tags DROP COLUMN T2UsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- T2Text -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'T2Text');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tags CHANGE COLUMN T2Text text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tags DROP COLUMN T2Text', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- T2Comment -> comment
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'T2Comment');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tags' AND column_name = 'comment');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tags CHANGE COLUMN T2Comment comment varchar(200) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tags DROP COLUMN T2Comment', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- TtTxID -> text_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tag_map' AND column_name = 'TtTxID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tag_map' AND column_name = 'text_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tag_map CHANGE COLUMN TtTxID text_id smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tag_map DROP COLUMN TtTxID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- TtT2ID -> text_tag_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tag_map' AND column_name = 'TtT2ID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'text_tag_map' AND column_name = 'text_tag_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE text_tag_map CHANGE COLUMN TtT2ID text_tag_id smallint(5) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE text_tag_map DROP COLUMN TtT2ID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate FKs against the new column names.
ALTER TABLE tags ADD CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;
ALTER TABLE text_tags ADD CONSTRAINT fk_text_tags_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;
ALTER TABLE text_tag_map ADD CONSTRAINT fk_text_tag_map_tag FOREIGN KEY (text_tag_id) REFERENCES text_tags(id) ON DELETE CASCADE;
ALTER TABLE word_tag_map ADD CONSTRAINT fk_word_tag_map_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE;
ALTER TABLE word_tag_map ADD CONSTRAINT fk_word_tag_map_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;

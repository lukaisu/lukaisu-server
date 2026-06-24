-- Rename news_feeds (`Nf*`) and feed_links (`Fl*`) columns to snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- The two tables are coupled by feed_links.FlNfID -> news_feeds.NfID, so the FK is
-- dropped first and recreated against the new names. Reserved words avoided:
--   NfUpdate -> update_interval (UPDATE is reserved); FlDate -> published_at.

-- Drop FKs that name the columns being renamed (both historical FK spellings).
ALTER TABLE feed_links DROP FOREIGN KEY IF EXISTS fk_feed_links_news_feed;
ALTER TABLE feed_links DROP FOREIGN KEY IF EXISTS fk_feed_links_newsfeed;
ALTER TABLE news_feeds DROP FOREIGN KEY IF EXISTS fk_news_feeds_user;

SET @db = DATABASE();

-- NfID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfID id tinyint(3) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfLgID -> language_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfLgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'language_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfLgID language_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfLgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfName -> name
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfName');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'name');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfName name varchar(40) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfName', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfSourceURI -> source_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfSourceURI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'source_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfSourceURI source_uri varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfSourceURI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfArticleSectionTags -> article_section_tags
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfArticleSectionTags');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'article_section_tags');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfArticleSectionTags article_section_tags text NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfArticleSectionTags', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfFilterTags -> filter_tags
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfFilterTags');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'filter_tags');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfFilterTags filter_tags text NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfFilterTags', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfUpdate -> update_interval
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfUpdate');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'update_interval');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfUpdate update_interval int(12) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfUpdate', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- NfOptions -> options
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'NfOptions');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'news_feeds' AND column_name = 'options');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE news_feeds CHANGE COLUMN NfOptions options varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE news_feeds DROP COLUMN NfOptions', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- FlID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlID id mediumint(8) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlTitle -> title
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlTitle');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'title');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlTitle title varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlTitle', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlLink -> link
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlLink');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'link');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlLink link varchar(400) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlLink', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlDescription -> description
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlDescription');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'description');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlDescription description text NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlDescription', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlDate -> published_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlDate');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'published_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlDate published_at datetime NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlDate', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlAudio -> audio
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlAudio');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'audio');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlAudio audio varchar(200) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlAudio', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlText -> text
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlText');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'text');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlText text longtext NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlText', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FlNfID -> feed_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'FlNfID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'feed_links' AND column_name = 'feed_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE feed_links CHANGE COLUMN FlNfID feed_id tinyint(3) unsigned NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE feed_links DROP COLUMN FlNfID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate FKs against the new column names.
ALTER TABLE news_feeds ADD CONSTRAINT fk_news_feeds_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;
ALTER TABLE feed_links ADD CONSTRAINT fk_feed_links_news_feed FOREIGN KEY (feed_id) REFERENCES news_feeds(id) ON DELETE CASCADE;

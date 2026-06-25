-- Rename the languages table columns from the legacy Lg* prefix to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist; no-op when neither exists. Indexes follow the column rename automatically.
--
-- languages is a heavily-referenced parent: renaming its PK LgID -> id means every
-- child FK pointing at languages(LgID) must be dropped first and recreated against
-- languages(id) afterwards. The self FK fk_languages_user (LgUsID -> users.UsID) is
-- likewise dropped before renaming LgUsID and recreated against the new column.
--
-- The piper_voice_id / lemmatizer_type columns only exist on installs that ran the
-- add_piper_voice / add_lemmatizer_config migrations; the guarded blocks no-op where
-- they are absent. fk_books_language is recreated only when the books table exists.
--
-- FKs touched (dropped before the renames, recreated against the new names after):
--   child side -> languages(LgID):  texts.fk_texts_language, words.fk_words_language,
--                  sentences.fk_sentences_language, news_feeds.fk_news_feeds_language,
--                  books.fk_books_language (books-feature installs only)
--   self:          languages.fk_languages_user (LgUsID -> users.UsID)
SET @db = DATABASE();

ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_language;
ALTER TABLE words DROP FOREIGN KEY IF EXISTS fk_words_language;
ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_language;
ALTER TABLE news_feeds DROP FOREIGN KEY IF EXISTS fk_news_feeds_language;
ALTER TABLE languages DROP FOREIGN KEY IF EXISTS fk_languages_user;

-- fk_books_language only exists on books-feature installs; guard the table existence.
SET @has_books_tbl = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'books');
SET @has_books_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'books' AND constraint_name = 'fk_books_language');
SET @sql = IF(@has_books_tbl > 0 AND @has_books_fk > 0, 'ALTER TABLE books DROP FOREIGN KEY fk_books_language', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db = DATABASE();

-- LgID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgID id tinyint(3) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgName -> name
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgName');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'name');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgName name varchar(40) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgName', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgDict1URI -> dict1_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgDict1URI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'dict1_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgDict1URI dict1_uri varchar(200) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgDict1URI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgDict2URI -> dict2_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgDict2URI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'dict2_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgDict2URI dict2_uri varchar(200) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgDict2URI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgGoogleTranslateURI -> google_translate_uri
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgGoogleTranslateURI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'google_translate_uri');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgGoogleTranslateURI google_translate_uri varchar(200) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgGoogleTranslateURI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgDict1PopUp -> dict1_popup
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgDict1PopUp');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'dict1_popup');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgDict1PopUp dict1_popup tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''Dictionary 1 opens in popup window''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgDict1PopUp', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgDict2PopUp -> dict2_popup
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgDict2PopUp');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'dict2_popup');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgDict2PopUp dict2_popup tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''Dictionary 2 opens in popup window''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgDict2PopUp', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgGoogleTranslatePopUp -> google_translate_popup
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgGoogleTranslatePopUp');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'google_translate_popup');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgGoogleTranslatePopUp google_translate_popup tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''Translator opens in popup window''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgGoogleTranslatePopUp', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgSourceLang -> source_lang
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgSourceLang');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'source_lang');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgSourceLang source_lang varchar(10) DEFAULT NULL COMMENT ''Source language code (BCP 47)''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgSourceLang', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgTargetLang -> target_lang
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgTargetLang');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'target_lang');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgTargetLang target_lang varchar(10) DEFAULT NULL COMMENT ''Target language code (BCP 47)''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgTargetLang', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgLocalDictMode -> local_dict_mode
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgLocalDictMode');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'local_dict_mode');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgLocalDictMode local_dict_mode tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT ''Local dictionary mode (0=online,1=local first,2=local only,3=combined)''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgLocalDictMode', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgExportTemplate -> export_template
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgExportTemplate');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'export_template');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgExportTemplate export_template varchar(1000) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgExportTemplate', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgTextSize -> text_size
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgTextSize');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'text_size');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgTextSize text_size smallint(5) unsigned NOT NULL DEFAULT 100', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgTextSize', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgCharacterSubstitutions -> character_substitutions
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgCharacterSubstitutions');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'character_substitutions');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgCharacterSubstitutions character_substitutions varchar(500) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgCharacterSubstitutions', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgRegexpSplitSentences -> regexp_split_sentences
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgRegexpSplitSentences');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'regexp_split_sentences');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgRegexpSplitSentences regexp_split_sentences varchar(500) NOT NULL DEFAULT ''.!?''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgRegexpSplitSentences', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgExceptionsSplitSentences -> exceptions_split_sentences
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgExceptionsSplitSentences');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'exceptions_split_sentences');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgExceptionsSplitSentences exceptions_split_sentences varchar(500) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgExceptionsSplitSentences', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgRegexpWordCharacters -> regexp_word_characters
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgRegexpWordCharacters');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'regexp_word_characters');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgRegexpWordCharacters regexp_word_characters varchar(500) NOT NULL DEFAULT ''a-zA-ZÀ-ÖØ-öø-ȳ''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgRegexpWordCharacters', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgParserType -> parser_type
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgParserType');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'parser_type');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgParserType parser_type varchar(50) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgParserType', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgRemoveSpaces -> remove_spaces
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgRemoveSpaces');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'remove_spaces');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgRemoveSpaces remove_spaces tinyint(1) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgRemoveSpaces', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgSplitEachChar -> split_each_char
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgSplitEachChar');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'split_each_char');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgSplitEachChar split_each_char tinyint(1) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgSplitEachChar', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgRightToLeft -> right_to_left
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgRightToLeft');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'right_to_left');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgRightToLeft right_to_left tinyint(1) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgRightToLeft', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgTTSVoiceAPI -> tts_voice_api
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgTTSVoiceAPI');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'tts_voice_api');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgTTSVoiceAPI tts_voice_api varchar(2048) NOT NULL DEFAULT ''''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgTTSVoiceAPI', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgShowRomanization -> show_romanization
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgShowRomanization');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'show_romanization');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgShowRomanization show_romanization tinyint(1) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgShowRomanization', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgPiperVoiceId -> piper_voice_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgPiperVoiceId');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'piper_voice_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgPiperVoiceId piper_voice_id varchar(100) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgPiperVoiceId', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- LgLemmatizerType -> lemmatizer_type
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'LgLemmatizerType');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'languages' AND column_name = 'lemmatizer_type');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE languages CHANGE COLUMN LgLemmatizerType lemmatizer_type varchar(20) DEFAULT ''dictionary''', IF(@old > 0 AND @new > 0, 'ALTER TABLE languages DROP COLUMN LgLemmatizerType', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the child FKs against languages(id) and the self FK against the renamed
-- user_id column. Child language_id columns were already renamed by earlier stages.
ALTER TABLE texts ADD CONSTRAINT fk_texts_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;
ALTER TABLE words ADD CONSTRAINT fk_words_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;
ALTER TABLE sentences ADD CONSTRAINT fk_sentences_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;
ALTER TABLE news_feeds ADD CONSTRAINT fk_news_feeds_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;
ALTER TABLE languages ADD CONSTRAINT fk_languages_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE;

-- Recreate fk_books_language only when the books table and its language_id column are
-- present (books-feature installs); RESTRICT matches the original constraint.
SET @has_books_tbl = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'books');
SET @has_books_lang_col = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'books' AND column_name = 'language_id');
SET @has_books_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'books' AND constraint_name = 'fk_books_language');
SET @sql = IF(@has_books_tbl > 0 AND @has_books_lang_col > 0 AND @has_books_fk = 0, 'ALTER TABLE books ADD CONSTRAINT fk_books_language FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

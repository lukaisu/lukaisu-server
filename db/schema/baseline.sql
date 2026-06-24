-- Basefile to install Lukaisu Server

-- Migration tracking table
-- Migrations are discovered from db/migrations/*.sql files and tracked here when applied
-- The checksum column stores SHA-256 hash for integrity validation
CREATE TABLE IF NOT EXISTS _migrations (
	filename VARCHAR(255) NOT NULL,
	applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	checksum VARCHAR(64) DEFAULT NULL,
	PRIMARY KEY (filename)
);

-- Database definition

-- Users table for multi-user authentication
CREATE TABLE IF NOT EXISTS users (
    UsID int(10) unsigned NOT NULL AUTO_INCREMENT,
    UsUsername varchar(100) NOT NULL,
    UsEmail varchar(255) DEFAULT NULL,
    UsPasswordHash varchar(255) DEFAULT NULL,
    UsApiToken varchar(64) DEFAULT NULL,
    UsApiTokenExpires datetime DEFAULT NULL,
    UsRememberToken varchar(64) DEFAULT NULL,
    UsRememberTokenExpires datetime DEFAULT NULL,
    UsPasswordResetToken varchar(64) DEFAULT NULL,
    UsPasswordResetTokenExpires datetime DEFAULT NULL,
    UsRecoveryCodeHash varchar(255) DEFAULT NULL,
    UsWordPressId int(10) unsigned DEFAULT NULL,
    UsGoogleId varchar(255) DEFAULT NULL,
    UsMicrosoftId varchar(255) DEFAULT NULL,
    UsCreated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UsLastLogin timestamp NULL DEFAULT NULL,
    UsIsActive tinyint(1) unsigned NOT NULL DEFAULT 1,
    UsRole enum('user','admin') NOT NULL DEFAULT 'user',
    PRIMARY KEY (UsID),
    UNIQUE KEY UsUsername (UsUsername),
    UNIQUE KEY UsEmail (UsEmail),
    UNIQUE KEY UsApiToken (UsApiToken),
    UNIQUE KEY UsRememberToken (UsRememberToken),
    UNIQUE KEY UsPasswordResetToken (UsPasswordResetToken),
    KEY UsWordPressId (UsWordPressId),
    UNIQUE KEY UsGoogleId (UsGoogleId),
    UNIQUE KEY UsMicrosoftId (UsMicrosoftId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: Admin user should be created through the setup wizard or registration page.
-- For security, no default admin is inserted. Multi-user mode requires explicit setup.

CREATE TABLE IF NOT EXISTS languages (
    LgID tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    LgUsID int(10) unsigned DEFAULT NULL,
    LgName varchar(40) NOT NULL,
    LgDict1URI varchar(200) NOT NULL,
    LgDict2URI varchar(200) DEFAULT NULL,
    LgGoogleTranslateURI varchar(200) DEFAULT NULL,
    LgDict1PopUp tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Dictionary 1 opens in popup window',
    LgDict2PopUp tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Dictionary 2 opens in popup window',
    LgGoogleTranslatePopUp tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Translator opens in popup window',
    LgSourceLang varchar(10) DEFAULT NULL COMMENT 'Source language code (BCP 47)',
    LgTargetLang varchar(10) DEFAULT NULL COMMENT 'Target language code (BCP 47)',
    LgLocalDictMode tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Local dictionary mode (0=online,1=local first,2=local only,3=combined)',
    LgExportTemplate varchar(1000) DEFAULT NULL,
    LgTextSize smallint(5) unsigned NOT NULL DEFAULT '100',
    LgCharacterSubstitutions varchar(500) NOT NULL DEFAULT '',
    LgRegexpSplitSentences varchar(500) NOT NULL DEFAULT '.!?',
    LgExceptionsSplitSentences varchar(500) NOT NULL DEFAULT '',
    LgRegexpWordCharacters varchar(500) NOT NULL DEFAULT 'a-zA-ZÀ-ÖØ-öø-ȳ',
    LgParserType varchar(50) DEFAULT NULL,
    LgRemoveSpaces tinyint(1) unsigned NOT NULL DEFAULT '0',
    LgSplitEachChar tinyint(1) unsigned NOT NULL DEFAULT '0',
    LgRightToLeft tinyint(1) unsigned NOT NULL DEFAULT '0',
    LgTTSVoiceAPI varchar(2048) NOT NULL DEFAULT '',
    LgShowRomanization tinyint(1) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (LgID),
    KEY LgUsID (LgUsID),
    UNIQUE KEY LgName (LgName),
    CONSTRAINT fk_languages_user FOREIGN KEY (LgUsID) REFERENCES users(UsID) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS sentences (
    SeID mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    SeLgID tinyint(3) unsigned NOT NULL,
    SeTxID smallint(5) unsigned NOT NULL,
    SeOrder smallint(5) unsigned NOT NULL,
    SeText text,
    SeFirstPos smallint(5) unsigned NOT NULL,
    PRIMARY KEY (SeID),
    KEY SeLgID (SeLgID),
    KEY SeTxID (SeTxID),
    KEY SeOrder (SeOrder)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS settings (
    StKey varchar(40) NOT NULL,
    StUsID int(10) unsigned NOT NULL DEFAULT 0,
    StValue varchar(40) DEFAULT NULL,
    PRIMARY KEY (StKey, StUsID)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS word_occurrences (
    Ti2WoID mediumint(8) unsigned DEFAULT NULL,
    Ti2LgID tinyint(3) unsigned NOT NULL,
    Ti2TxID smallint(5) unsigned NOT NULL,
    Ti2SeID mediumint(8) unsigned NOT NULL,
    Ti2Order smallint(5) unsigned NOT NULL,
    Ti2WordCount tinyint(3) unsigned NOT NULL,
    Ti2Text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    PRIMARY KEY (Ti2TxID,Ti2Order,Ti2WordCount), KEY Ti2WoID (Ti2WoID)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS temp_word_occurrences (
    TiCount smallint(5) unsigned NOT NULL,
    TiSeID mediumint(8) unsigned NOT NULL,
    TiOrder smallint(5) unsigned NOT NULL,
    TiWordCount tinyint(3) unsigned NOT NULL,
    TiText varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS temp_words (
    text varchar(250) DEFAULT NULL,
    text_lc varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    translation varchar(500) NOT NULL DEFAULT '*',
    romanization varchar(100) DEFAULT NULL,
    sentence varchar(1000) DEFAULT NULL,
    tag_list varchar(255) DEFAULT NULL,
    PRIMARY KEY(text_lc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS texts (
    TxID smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    TxUsID int(10) unsigned DEFAULT NULL,
    TxLgID tinyint(3) unsigned NOT NULL,
    TxTitle varchar(200) NOT NULL,
    TxText text NOT NULL,
    TxAnnotatedText longtext NOT NULL DEFAULT '',
    TxAudioURI varchar(2048) DEFAULT NULL,
    TxSourceURI varchar(1000) DEFAULT NULL,
    TxPosition smallint(5) DEFAULT 0,
    TxAudioPosition float DEFAULT 0,
    TxArchivedAt DATETIME DEFAULT NULL,
    PRIMARY KEY (TxID),
    KEY TxUsID (TxUsID),
    KEY TxLgID (TxLgID),
    KEY TxLgIDSourceURI (TxSourceURI(20),TxLgID),
    KEY TxArchivedAt (TxArchivedAt),
    CONSTRAINT fk_texts_user FOREIGN KEY (TxUsID) REFERENCES users(UsID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS words (
    id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    language_id tinyint(3) unsigned NOT NULL,
    text varchar(250) NOT NULL,
    text_lc varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    lemma varchar(250) DEFAULT NULL,
    lemma_lc varchar(250) DEFAULT NULL,
    status tinyint(4) NOT NULL,
    translation varchar(500) NOT NULL DEFAULT '*',
    romanization varchar(100) DEFAULT NULL,
    sentence varchar(1000) DEFAULT NULL,
    notes varchar(1000) DEFAULT NULL,
    word_count tinyint(3) unsigned NOT NULL DEFAULT 0,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_changed_at timestamp NOT NULL DEFAULT '1970-01-01 12:00:00',
    today_score double NOT NULL DEFAULT '0',
    tomorrow_score double NOT NULL DEFAULT '0',
    random double NOT NULL DEFAULT '0',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY WoTextLCLgID (text_lc,language_id),
    KEY language_id (language_id),
    KEY status (status),
    KEY translation (translation(20)),
    KEY created_at (created_at),
    KEY status_changed_at (status_changed_at),
    KEY word_count(word_count),
    KEY today_score (today_score),
    KEY tomorrow_score (tomorrow_score),
    KEY random (random),
    KEY idx_words_lemma (lemma_lc, language_id),
    CONSTRAINT fk_words_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS tags (
    TgID smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    TgUsID int(10) unsigned DEFAULT NULL,
    TgText varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    TgComment varchar(200) NOT NULL DEFAULT '',
    PRIMARY KEY (TgID),
    KEY TgUsID (TgUsID),
    UNIQUE KEY TgText (TgText),
    CONSTRAINT fk_tags_user FOREIGN KEY (TgUsID) REFERENCES users(UsID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS word_tag_map (
    WtWoID mediumint(8) unsigned NOT NULL,
    WtTgID smallint(5) unsigned NOT NULL,
    PRIMARY KEY (WtWoID,WtTgID),
    KEY WtTgID (WtTgID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_tags (
    T2ID smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    T2UsID int(10) unsigned DEFAULT NULL,
    T2Text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    T2Comment varchar(200) NOT NULL DEFAULT '',
    PRIMARY KEY (T2ID),
    KEY T2UsID (T2UsID),
    UNIQUE KEY T2Text (T2Text),
    CONSTRAINT fk_text_tags_user FOREIGN KEY (T2UsID) REFERENCES users(UsID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_tag_map (
    TtTxID smallint(5) unsigned NOT NULL,
    TtT2ID smallint(5) unsigned NOT NULL,
    PRIMARY KEY (TtTxID,TtT2ID), KEY TtT2ID (TtT2ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_feeds (
    id tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    language_id tinyint(3) unsigned NOT NULL,
    name varchar(40) NOT NULL,
    source_uri varchar(200) NOT NULL,
    article_section_tags text NOT NULL,
    filter_tags text NOT NULL,
    update_interval int(12) unsigned NOT NULL,
    options varchar(200) NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY language_id (language_id),
    KEY update_interval (update_interval),
    CONSTRAINT fk_news_feeds_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_links (
    id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(200) NOT NULL,
    link varchar(400) NOT NULL,
    description text NOT NULL,
    published_at datetime NOT NULL,
    audio varchar(200) NOT NULL DEFAULT '',
    text longtext NOT NULL DEFAULT '',
    feed_id tinyint(3) unsigned NOT NULL,
    PRIMARY KEY (id),
    KEY link (link),
    KEY published_at (published_at),
    UNIQUE KEY title (feed_id,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Whisper transcription job ownership map (binds NLP job_id to the
-- caller). Without this, /api/v1/whisper/status|result|cancel would
-- accept any client-supplied job_id with no per-user check.
CREATE TABLE IF NOT EXISTS whisper_jobs (
    job_id varchar(64) NOT NULL,
    user_id int(10) unsigned DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_whisper_jobs_user (user_id),
    CONSTRAINT fk_whisper_jobs_user FOREIGN KEY (user_id)
        REFERENCES users(UsID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily learning-activity counters (one row per user per calendar date).
CREATE TABLE IF NOT EXISTS activity_log (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL COMMENT 'User ID (NULL in single-user mode)',
    date date NOT NULL COMMENT 'Activity date',
    terms_created int(10) unsigned NOT NULL DEFAULT 0,
    terms_reviewed int(10) unsigned NOT NULL DEFAULT 0,
    texts_read int(10) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activity_user_date (user_id, date),
    KEY idx_activity_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prefix migration tracking table for multi-user conversion
CREATE TABLE IF NOT EXISTS _prefix_migration_log (
    prefix VARCHAR(40) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    tables_migrated INT DEFAULT 0,
    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Inter-table foreign key constraints
-- NOTE: FK constraints are added via migration 20251221_120000_add_inter_table_foreign_keys.sql
-- This ensures they are only applied once and allows for proper data cleanup.
-- The migration adds FK constraints:
-- - Language references: texts, words, sentences, news_feeds -> languages
-- - Text references: sentences, word_occurrences, text_tag_map -> texts
-- - Other: word_occurrences -> sentences, word_occurrences -> words (SET NULL),
--   word_tag_map -> words/tags, text_tag_map -> text_tags, feed_links -> news_feeds
-- ============================================================================

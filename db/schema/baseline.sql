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
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    username varchar(100) NOT NULL,
    email varchar(255) DEFAULT NULL,
    email_verified_at datetime DEFAULT NULL,
    email_verification_token varchar(255) DEFAULT NULL,
    email_verification_token_expires datetime DEFAULT NULL,
    password_hash varchar(255) DEFAULT NULL,
    api_token varchar(64) DEFAULT NULL,
    api_token_expires datetime DEFAULT NULL,
    remember_token varchar(64) DEFAULT NULL,
    remember_token_expires datetime DEFAULT NULL,
    password_reset_token varchar(64) DEFAULT NULL,
    password_reset_token_expires datetime DEFAULT NULL,
    recovery_code_hash varchar(255) DEFAULT NULL,
    wordpress_id int(10) unsigned DEFAULT NULL,
    google_id varchar(255) DEFAULT NULL,
    microsoft_id varchar(255) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at timestamp NULL DEFAULT NULL,
    is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
    role enum('user','admin') NOT NULL DEFAULT 'user',
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    UNIQUE KEY api_token (api_token),
    UNIQUE KEY remember_token (remember_token),
    UNIQUE KEY password_reset_token (password_reset_token),
    KEY wordpress_id (wordpress_id),
    UNIQUE KEY google_id (google_id),
    UNIQUE KEY microsoft_id (microsoft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: Admin user should be created through the setup wizard or registration page.
-- For security, no default admin is inserted. Multi-user mode requires explicit setup.

CREATE TABLE IF NOT EXISTS languages (
    id tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    name varchar(40) NOT NULL,
    dict1_uri varchar(200) NOT NULL,
    dict2_uri varchar(200) DEFAULT NULL,
    google_translate_uri varchar(200) DEFAULT NULL,
    dict1_popup tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Dictionary 1 opens in popup window',
    dict2_popup tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Dictionary 2 opens in popup window',
    google_translate_popup tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Translator opens in popup window',
    source_lang varchar(10) DEFAULT NULL COMMENT 'Source language code (BCP 47)',
    target_lang varchar(10) DEFAULT NULL COMMENT 'Target language code (BCP 47)',
    local_dict_mode tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Local dictionary mode (0=online,1=local first,2=local only,3=combined)',
    export_template varchar(1000) DEFAULT NULL,
    text_size smallint(5) unsigned NOT NULL DEFAULT '100',
    character_substitutions varchar(500) NOT NULL DEFAULT '',
    regexp_split_sentences varchar(500) NOT NULL DEFAULT '.!?',
    exceptions_split_sentences varchar(500) NOT NULL DEFAULT '',
    regexp_word_characters varchar(500) NOT NULL DEFAULT 'a-zA-ZÀ-ÖØ-öø-ȳ',
    parser_type varchar(50) DEFAULT NULL,
    remove_spaces tinyint(1) unsigned NOT NULL DEFAULT '0',
    split_each_char tinyint(1) unsigned NOT NULL DEFAULT '0',
    right_to_left tinyint(1) unsigned NOT NULL DEFAULT '0',
    tts_voice_api varchar(2048) NOT NULL DEFAULT '',
    show_romanization tinyint(1) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY name (name),
    CONSTRAINT fk_languages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS sentences (
    id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    language_id tinyint(3) unsigned NOT NULL,
    text_id smallint(5) unsigned NOT NULL,
    position smallint(5) unsigned NOT NULL,
    text text,
    first_pos smallint(5) unsigned NOT NULL,
    PRIMARY KEY (id),
    KEY language_id (language_id),
    KEY text_id (text_id),
    KEY position (position)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS settings (
    name varchar(40) NOT NULL,
    user_id int(10) unsigned NOT NULL DEFAULT 0,
    value varchar(40) DEFAULT NULL,
    PRIMARY KEY (name, user_id)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS word_occurrences (
    word_id mediumint(8) unsigned DEFAULT NULL,
    language_id tinyint(3) unsigned NOT NULL,
    text_id smallint(5) unsigned NOT NULL,
    sentence_id mediumint(8) unsigned NOT NULL,
    position smallint(5) unsigned NOT NULL,
    word_count tinyint(3) unsigned NOT NULL,
    text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    PRIMARY KEY (text_id,position,word_count), KEY word_id (word_id)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS temp_word_occurrences (
    char_position smallint(5) unsigned NOT NULL,
    sentence_id mediumint(8) unsigned NOT NULL,
    position smallint(5) unsigned NOT NULL,
    word_count tinyint(3) unsigned NOT NULL,
    text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
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
    id smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    language_id tinyint(3) unsigned NOT NULL,
    title varchar(200) NOT NULL,
    text text NOT NULL,
    annotated_text longtext NOT NULL DEFAULT '',
    audio_uri varchar(2048) DEFAULT NULL,
    source_uri varchar(1000) DEFAULT NULL,
    position smallint(5) DEFAULT 0,
    audio_position float DEFAULT 0,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY language_id (language_id),
    KEY source_uri_language_id (source_uri(20),language_id),
    KEY archived_at (archived_at),
    CONSTRAINT fk_texts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    stability double NOT NULL DEFAULT 0,
    difficulty double NOT NULL DEFAULT 0,
    due_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_reviewed_at datetime DEFAULT NULL,
    reps smallint(5) unsigned NOT NULL DEFAULT 0,
    lapses smallint(5) unsigned NOT NULL DEFAULT 0,
    fsrs_state tinyint(3) unsigned NOT NULL DEFAULT 0,
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
    KEY due_at (due_at),
    KEY idx_words_lemma (lemma_lc, language_id),
    CONSTRAINT fk_words_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- FSRS review log (issue #238): one row per graded answer, for stats and a
-- future per-user parameter optimiser. The scheduler itself runs client-side.
CREATE TABLE IF NOT EXISTS review_log (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    word_id mediumint(8) unsigned NOT NULL,
    user_id int(10) unsigned DEFAULT NULL,
    grade tinyint(3) unsigned NOT NULL,
    fsrs_state tinyint(3) unsigned NOT NULL,
    stability double NOT NULL,
    difficulty double NOT NULL,
    elapsed_days double NOT NULL DEFAULT 0,
    scheduled_days double NOT NULL DEFAULT 0,
    reviewed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY word_id (word_id),
    KEY user_id (user_id),
    KEY reviewed_at (reviewed_at),
    CONSTRAINT fk_review_log_word FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tags (
    id smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    comment varchar(200) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY text (text),
    CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS word_tag_map (
    word_id mediumint(8) unsigned NOT NULL,
    tag_id smallint(5) unsigned NOT NULL,
    PRIMARY KEY (word_id,tag_id),
    KEY tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_tags (
    id smallint(5) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned DEFAULT NULL,
    text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    comment varchar(200) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY text (text),
    CONSTRAINT fk_text_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_tag_map (
    text_id smallint(5) unsigned NOT NULL,
    text_tag_id smallint(5) unsigned NOT NULL,
    PRIMARY KEY (text_id,text_tag_id), KEY text_tag_id (text_tag_id)
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
    CONSTRAINT fk_news_feeds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
        REFERENCES users(id) ON DELETE CASCADE
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

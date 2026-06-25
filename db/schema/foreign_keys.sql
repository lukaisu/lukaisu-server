-- The complete set of foreign keys for the modern (snake_case) schema.
--
-- baseline.sql defines tables and indexes only; every foreign key (user-scope and
-- inter-table) lives here so there is a single source of truth and so each FK can
-- be applied once every table exists (a CREATE TABLE cannot reference a table
-- defined later in the same file). The runner applies this file after the schema
-- is (re)built — a fresh install, a backup restore, or a legacy upgrade — see
-- Migrations::applyForeignKeys / Migrations::checkAndUpdate. It is NOT run on a
-- normal boot. tests/setup_test_db.php reads the same file so the test database
-- matches a real install.
--
-- These statements are ADD-only (no DROP), on purpose: applyForeignKeys is
-- best-effort, so on a legacy upgrade — where the migration chain has already
-- created some of these FKs, and where some ADDs fail on transitional column-type
-- or orphan-data issues — a failed ADD must never leave a constraint dropped. On a
-- fresh install or restore the tables carry no FKs yet (a restore drops them all
-- first), so every ADD applies cleanly. A duplicate-name ADD simply fails and is
-- ignored. All FK columns already match their parent primary-key types in
-- baseline.sql.

-- ============================================================================
-- User-scope foreign keys (ON DELETE CASCADE): deleting a user removes their rows.
-- ============================================================================
ALTER TABLE languages
    ADD CONSTRAINT fk_languages_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE texts
    ADD CONSTRAINT fk_texts_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE words
    ADD CONSTRAINT fk_words_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE tags
    ADD CONSTRAINT fk_tags_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE text_tags
    ADD CONSTRAINT fk_text_tags_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE news_feeds
    ADD CONSTRAINT fk_news_feeds_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE whisper_jobs
    ADD CONSTRAINT fk_whisper_jobs_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE review_log
    ADD CONSTRAINT fk_review_log_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE local_dictionaries
    ADD CONSTRAINT fk_local_dict_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- ============================================================================
-- Review log -> word (ON DELETE CASCADE).
-- ============================================================================
ALTER TABLE review_log
    ADD CONSTRAINT fk_review_log_word
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE;

-- ============================================================================
-- Local dictionaries -> language, entries -> dictionary (ON DELETE CASCADE).
-- ============================================================================
ALTER TABLE local_dictionaries
    ADD CONSTRAINT fk_local_dict_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE local_dictionary_entries
    ADD CONSTRAINT fk_entry_dictionary
    FOREIGN KEY (local_dictionary_id) REFERENCES local_dictionaries(id) ON DELETE CASCADE;

-- ============================================================================
-- Language references (ON DELETE CASCADE): deleting a language removes its content.
-- ============================================================================
ALTER TABLE texts
    ADD CONSTRAINT fk_texts_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE words
    ADD CONSTRAINT fk_words_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE sentences
    ADD CONSTRAINT fk_sentences_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE news_feeds
    ADD CONSTRAINT fk_news_feeds_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

-- ============================================================================
-- Text references (ON DELETE CASCADE): deleting a text removes its sentences,
-- word occurrences and tag links.
-- ============================================================================
ALTER TABLE sentences
    ADD CONSTRAINT fk_sentences_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

ALTER TABLE text_tag_map
    ADD CONSTRAINT fk_text_tag_map_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

-- ============================================================================
-- Sentence reference (ON DELETE CASCADE).
-- ============================================================================
ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_sentence
    FOREIGN KEY (sentence_id) REFERENCES sentences(id) ON DELETE CASCADE;

-- ============================================================================
-- Word reference (ON DELETE SET NULL): deleting a word makes its occurrences
-- "unknown" again (word_id is nullable in baseline.sql).
-- ============================================================================
ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_word
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL;

-- ============================================================================
-- Tag maps and feed links (ON DELETE CASCADE).
-- ============================================================================
ALTER TABLE word_tag_map
    ADD CONSTRAINT fk_word_tag_map_word
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE;

ALTER TABLE word_tag_map
    ADD CONSTRAINT fk_word_tag_map_tag
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;

ALTER TABLE text_tag_map
    ADD CONSTRAINT fk_text_tag_map_text_tag
    FOREIGN KEY (text_tag_id) REFERENCES text_tags(id) ON DELETE CASCADE;

ALTER TABLE feed_links
    ADD CONSTRAINT fk_feed_links_newsfeed
    FOREIGN KEY (feed_id) REFERENCES news_feeds(id) ON DELETE CASCADE;

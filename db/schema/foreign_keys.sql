-- Inter-table foreign key constraints for the modern (snake_case) schema.
--
-- baseline.sql creates every table with its user-scope FK inline, but defers the
-- inter-table content FKs to here so a fresh install can apply them in one place
-- AFTER all tables exist (a CREATE TABLE cannot reference a table defined later in
-- the file). The runner applies this file once, on a fresh install, right after
-- baseline.sql (see Migrations::applyForeignKeys); a legacy LWT upgrade gets the
-- equivalent FKs from the rename migrations instead, so this file is NOT part of
-- the per-boot rebuild. tests/setup_test_db.php reads the same file so the test
-- database and a real fresh install stay in sync.
--
-- All FK columns already match their parent primary-key types in baseline.sql, so
-- no column-type changes are needed here. Each constraint is idempotent
-- (DROP ... IF EXISTS, then ADD) so re-applying the file is safe.

-- Language references (ON DELETE CASCADE): deleting a language removes its content.
ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_language;
ALTER TABLE texts
    ADD CONSTRAINT fk_texts_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE words DROP FOREIGN KEY IF EXISTS fk_words_language;
ALTER TABLE words
    ADD CONSTRAINT fk_words_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_language;
ALTER TABLE sentences
    ADD CONSTRAINT fk_sentences_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

ALTER TABLE news_feeds DROP FOREIGN KEY IF EXISTS fk_news_feeds_language;
ALTER TABLE news_feeds
    ADD CONSTRAINT fk_news_feeds_language
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE;

-- Text references (ON DELETE CASCADE): deleting a text removes its sentences,
-- word occurrences and tag links.
ALTER TABLE sentences DROP FOREIGN KEY IF EXISTS fk_sentences_text;
ALTER TABLE sentences
    ADD CONSTRAINT fk_sentences_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_text;
ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

ALTER TABLE text_tag_map DROP FOREIGN KEY IF EXISTS fk_text_tag_map_text;
ALTER TABLE text_tag_map
    ADD CONSTRAINT fk_text_tag_map_text
    FOREIGN KEY (text_id) REFERENCES texts(id) ON DELETE CASCADE;

-- Sentence reference (ON DELETE CASCADE).
ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_sentence;
ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_sentence
    FOREIGN KEY (sentence_id) REFERENCES sentences(id) ON DELETE CASCADE;

-- Word reference (ON DELETE SET NULL): deleting a word makes its occurrences
-- "unknown" again (word_id is nullable in baseline.sql).
ALTER TABLE word_occurrences DROP FOREIGN KEY IF EXISTS fk_word_occurrences_word;
ALTER TABLE word_occurrences
    ADD CONSTRAINT fk_word_occurrences_word
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL;

-- Word tags (ON DELETE CASCADE).
ALTER TABLE word_tag_map DROP FOREIGN KEY IF EXISTS fk_word_tag_map_word;
ALTER TABLE word_tag_map
    ADD CONSTRAINT fk_word_tag_map_word
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE;

ALTER TABLE word_tag_map DROP FOREIGN KEY IF EXISTS fk_word_tag_map_tag;
ALTER TABLE word_tag_map
    ADD CONSTRAINT fk_word_tag_map_tag
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;

-- Text tags (ON DELETE CASCADE).
ALTER TABLE text_tag_map DROP FOREIGN KEY IF EXISTS fk_text_tag_map_text_tag;
ALTER TABLE text_tag_map
    ADD CONSTRAINT fk_text_tag_map_text_tag
    FOREIGN KEY (text_tag_id) REFERENCES text_tags(id) ON DELETE CASCADE;

-- Feed links (ON DELETE CASCADE).
ALTER TABLE feed_links DROP FOREIGN KEY IF EXISTS fk_feed_links_newsfeed;
ALTER TABLE feed_links
    ADD CONSTRAINT fk_feed_links_newsfeed
    FOREIGN KEY (feed_id) REFERENCES news_feeds(id) ON DELETE CASCADE;

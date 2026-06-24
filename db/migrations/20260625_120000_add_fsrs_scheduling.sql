-- Add FSRS scheduling state to words + a review_log table (issue #238, Phase 2).
--
-- The spaced-repetition scheduler runs client-side (ts-fsrs); the server stores
-- the resulting card fields and selects due rows. The legacy Leitner score
-- columns (today_score / tomorrow_score / random) are left in place for now and
-- dropped in a follow-up once nothing reads them. Idempotent via IF NOT EXISTS.

ALTER TABLE words
    ADD COLUMN IF NOT EXISTS stability double NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS difficulty double NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS due_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS last_reviewed_at datetime DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reps smallint(5) unsigned NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS lapses smallint(5) unsigned NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fsrs_state tinyint(3) unsigned NOT NULL DEFAULT 0;

ALTER TABLE words ADD INDEX IF NOT EXISTS due_at (due_at);

-- Seed FSRS state from existing status + status_changed_at, mirroring the client
-- fsrsForStatus() and the offline Dexie upgrade, so the derived display status
-- (and reading colours) stay stable right after migration:
--   1   => a new card, due now;
--   2-5 => a Review card seeded so statusFromStability() returns the same status;
--   98/99 => unscheduled (status filters them out of review anyway).
UPDATE words SET
    stability = CASE `status`
        WHEN 1 THEN 0 WHEN 2 THEN 3 WHEN 3 THEN 15 WHEN 4 THEN 60 WHEN 5 THEN 120 ELSE 0 END,
    difficulty = CASE WHEN `status` BETWEEN 2 AND 5 THEN 5 ELSE 0 END,
    due_at = CASE `status`
        WHEN 2 THEN status_changed_at + INTERVAL 3 DAY
        WHEN 3 THEN status_changed_at + INTERVAL 15 DAY
        WHEN 4 THEN status_changed_at + INTERVAL 60 DAY
        WHEN 5 THEN status_changed_at + INTERVAL 120 DAY
        ELSE status_changed_at END,
    last_reviewed_at = CASE WHEN `status` BETWEEN 2 AND 5 THEN status_changed_at ELSE NULL END,
    reps = CASE WHEN `status` BETWEEN 2 AND 5 THEN 1 ELSE 0 END,
    lapses = 0,
    fsrs_state = CASE WHEN `status` BETWEEN 2 AND 5 THEN 2 ELSE 0 END;

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
    CONSTRAINT fk_review_log_user FOREIGN KEY (user_id) REFERENCES users(UsID) ON DELETE CASCADE
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Drop the legacy Leitner score columns from words (issue #238, Phase 2).
--
-- FSRS scheduling (stability / difficulty / due_at / last_reviewed_at / reps /
-- lapses / fsrs_state, added in 20260625_120000) fully replaced the Leitner
-- model. Nothing reads today_score / tomorrow_score / random anymore: review
-- due-selection is by due_at, the word list sorts by stability, imports rely on
-- the FSRS column defaults, and the official backup no longer exports them.
--
-- Dropping a column also drops its index, so the today_score / tomorrow_score /
-- random KEYs go with them. Idempotent via IF EXISTS (MariaDB extension),
-- matching the add-FSRS migration's style.

ALTER TABLE words
    DROP COLUMN IF EXISTS today_score,
    DROP COLUMN IF EXISTS tomorrow_score,
    DROP COLUMN IF EXISTS `random`;

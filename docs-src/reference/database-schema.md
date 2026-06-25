# Database schema

Reference for the Lukaisu Server database. **Generated from `db/schema/baseline.sql`** by `bin/generate-schema-doc.php` — do not edit by hand; re-run the generator after changing the baseline.

All tables use the InnoDB engine and UTF-8 (`utf8mb4`). Columns follow table-scoped `snake_case` naming (primary key `id`, foreign keys `<table>_id`); see `developer/schema-naming` for the convention.

## `_migrations`

Migration tracking table Migrations are discovered from db/migrations/*.sql files and tracked here when applied The checksum column stores SHA-256 hash for integrity validation

```sql
filename VARCHAR(255) NOT NULL,
applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
checksum VARCHAR(64) DEFAULT NULL,
PRIMARY KEY (filename)
```

## `users`

Users table for multi-user authentication

```sql
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
```

## `languages`

```sql
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
```

## `sentences`

```sql
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
```

## `settings`

```sql
name varchar(40) NOT NULL,
user_id int(10) unsigned NOT NULL DEFAULT 0,
value varchar(40) DEFAULT NULL,
PRIMARY KEY (name, user_id)
```

## `word_occurrences`

```sql
word_id mediumint(8) unsigned DEFAULT NULL,
language_id tinyint(3) unsigned NOT NULL,
text_id smallint(5) unsigned NOT NULL,
sentence_id mediumint(8) unsigned NOT NULL,
position smallint(5) unsigned NOT NULL,
word_count tinyint(3) unsigned NOT NULL,
text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
PRIMARY KEY (text_id,position,word_count), KEY word_id (word_id)
```

## `temp_word_occurrences`

```sql
char_position smallint(5) unsigned NOT NULL,
sentence_id mediumint(8) unsigned NOT NULL,
position smallint(5) unsigned NOT NULL,
word_count tinyint(3) unsigned NOT NULL,
text varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
```

## `temp_words`

```sql
text varchar(250) DEFAULT NULL,
text_lc varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
translation varchar(500) NOT NULL DEFAULT '*',
romanization varchar(100) DEFAULT NULL,
sentence varchar(1000) DEFAULT NULL,
tag_list varchar(255) DEFAULT NULL,
PRIMARY KEY(text_lc)
```

## `texts`

```sql
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
```

## `words`

```sql
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
```

## `review_log`

FSRS review log (issue #238): one row per graded answer, for stats and a future per-user parameter optimiser. The scheduler itself runs client-side.

```sql
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
```

## `tags`

```sql
id smallint(5) unsigned NOT NULL AUTO_INCREMENT,
user_id int(10) unsigned DEFAULT NULL,
text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
comment varchar(200) NOT NULL DEFAULT '',
PRIMARY KEY (id),
KEY user_id (user_id),
UNIQUE KEY text (text),
CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
```

## `word_tag_map`

```sql
word_id mediumint(8) unsigned NOT NULL,
tag_id smallint(5) unsigned NOT NULL,
PRIMARY KEY (word_id,tag_id),
KEY tag_id (tag_id)
```

## `text_tags`

```sql
id smallint(5) unsigned NOT NULL AUTO_INCREMENT,
user_id int(10) unsigned DEFAULT NULL,
text varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
comment varchar(200) NOT NULL DEFAULT '',
PRIMARY KEY (id),
KEY user_id (user_id),
UNIQUE KEY text (text),
CONSTRAINT fk_text_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
```

## `text_tag_map`

```sql
text_id smallint(5) unsigned NOT NULL,
text_tag_id smallint(5) unsigned NOT NULL,
PRIMARY KEY (text_id,text_tag_id), KEY text_tag_id (text_tag_id)
```

## `news_feeds`

```sql
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
```

## `feed_links`

```sql
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
```

## `whisper_jobs`

Whisper transcription job ownership map (binds NLP job_id to the caller). Without this, /api/v1/whisper/status|result|cancel would accept any client-supplied job_id with no per-user check.

```sql
job_id varchar(64) NOT NULL,
user_id int(10) unsigned DEFAULT NULL,
created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (job_id),
KEY idx_whisper_jobs_user (user_id),
CONSTRAINT fk_whisper_jobs_user FOREIGN KEY (user_id)
REFERENCES users(id) ON DELETE CASCADE
```

## `activity_log`

Daily learning-activity counters (one row per user per calendar date).

```sql
id int(10) unsigned NOT NULL AUTO_INCREMENT,
user_id int(10) unsigned DEFAULT NULL COMMENT 'User ID (NULL in single-user mode)',
date date NOT NULL COMMENT 'Activity date',
terms_created int(10) unsigned NOT NULL DEFAULT 0,
terms_reviewed int(10) unsigned NOT NULL DEFAULT 0,
texts_read int(10) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY (id),
UNIQUE KEY uq_activity_user_date (user_id, date),
KEY idx_activity_date (date)
```

## `local_dictionaries`

Imported offline/local dictionaries and their per-language metadata.

```sql
id int(10) unsigned NOT NULL AUTO_INCREMENT,
language_id tinyint(3) unsigned NOT NULL COMMENT 'Language this dictionary belongs to',
name varchar(100) NOT NULL COMMENT 'Dictionary name',
description varchar(500) DEFAULT NULL COMMENT 'Optional description',
source_format varchar(20) NOT NULL DEFAULT 'csv' COMMENT 'Original import format: csv, json, stardict',
entry_count int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Number of entries',
priority tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Lookup order (1=highest)',
enabled tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'Whether the dictionary is active',
created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
user_id int(10) unsigned DEFAULT NULL COMMENT 'User ID (NULL in single-user mode)',
PRIMARY KEY (id),
KEY idx_local_dict_language (language_id),
KEY idx_local_dict_user (user_id),
KEY idx_local_dict_enabled_priority (enabled, priority),
CONSTRAINT fk_local_dict_language FOREIGN KEY (language_id)
REFERENCES languages(id) ON DELETE CASCADE,
CONSTRAINT fk_local_dict_user FOREIGN KEY (user_id)
REFERENCES users(id) ON DELETE CASCADE
```

## `local_dictionary_entries`

Individual entries (headword -> definition) for a local dictionary.

```sql
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
local_dictionary_id int(10) unsigned NOT NULL COMMENT 'Owning dictionary',
term varchar(250) NOT NULL COMMENT 'Headword/term',
term_lc varchar(250) NOT NULL COMMENT 'Lowercased term for searching',
definition text NOT NULL COMMENT 'Definition/translation',
reading varchar(250) DEFAULT NULL COMMENT 'Pronunciation/reading (e.g. furigana)',
part_of_speech varchar(50) DEFAULT NULL COMMENT 'Part of speech',
PRIMARY KEY (id),
KEY idx_entry_dictionary (local_dictionary_id),
KEY idx_entry_term_lc (term_lc),
CONSTRAINT fk_entry_dictionary FOREIGN KEY (local_dictionary_id)
REFERENCES local_dictionaries(id) ON DELETE CASCADE
```

## `_prefix_migration_log`

Prefix migration tracking table for multi-user conversion

```sql
prefix VARCHAR(40) NOT NULL,
user_id INT UNSIGNED NOT NULL,
tables_migrated INT DEFAULT 0,
migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (prefix)
```

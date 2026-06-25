<?php

/**
 * Generate docs-src/reference/database-schema.md from db/schema/baseline.sql.
 *
 * The reference schema doc used to be hand-maintained and drifted badly out of
 * date (legacy table names, Hungarian column prefixes). It is now generated from
 * the authoritative baseline so it stays in sync: re-run this after changing
 * baseline.sql.
 *
 *   php bin/generate-schema-doc.php          # rewrite the doc in place
 *   php bin/generate-schema-doc.php --check  # exit non-zero if the doc is stale
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Bin
 * @license  Unlicense <http://unlicense.org/>
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$baselinePath = $root . '/db/schema/baseline.sql';
$docPath = $root . '/docs-src/reference/database-schema.md';
$check = in_array('--check', $argv ?? [], true);

// Optional --baseline=PATH to generate from a specific baseline (e.g. a committed
// revision exported with `git show`); defaults to the working-tree baseline.
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--baseline=')) {
        $baselinePath = substr($arg, strlen('--baseline='));
    }
}

$sql = file_get_contents($baselinePath);
if ($sql === false) {
    fwrite(STDERR, "Cannot read $baselinePath\n");
    exit(1);
}

$lines = explode("\n", $sql);
$tables = [];
$pendingComment = [];
$current = null;

foreach ($lines as $line) {
    $trimmed = trim($line);

    if ($current === null) {
        // Collect leading comment lines to use as the table description.
        if (str_starts_with($trimmed, '--')) {
            $text = trim(substr($trimmed, 2));
            if ($text !== '') {
                $pendingComment[] = $text;
            }
            continue;
        }
        if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\(/i', $trimmed, $m)) {
            $current = ['name' => $m[1], 'comment' => implode(' ', $pendingComment), 'body' => []];
            $pendingComment = [];
            continue;
        }
        // Any other line (blank or code) breaks a pending comment's association,
        // so only comments directly above a CREATE TABLE describe it (file and
        // section headers are separated by a blank line and are dropped).
        $pendingComment = [];
        continue;
    }

    // Inside a CREATE TABLE: a line beginning with ')' closes it (ENGINE may sit
    // on this or the following line).
    if (str_starts_with($trimmed, ')')) {
        $tables[$current['name']] = $current;
        $current = null;
        $pendingComment = [];
        continue;
    }
    if ($trimmed !== '') {
        $current['body'][] = rtrim($trimmed);
    }
}

$out = [];
$out[] = '# Database schema';
$out[] = '';
$out[] = 'Reference for the Lukaisu Server database. **Generated from'
    . ' `db/schema/baseline.sql`** by `bin/generate-schema-doc.php` — do not edit'
    . ' by hand; re-run the generator after changing the baseline.';
$out[] = '';
$out[] = 'All tables use the InnoDB engine and UTF-8 (`utf8mb4`). Columns follow'
    . ' table-scoped `snake_case` naming (primary key `id`, foreign keys'
    . ' `<table>_id`); see `developer/schema-naming` for the convention.';
$out[] = '';

foreach ($tables as $name => $t) {
    $out[] = '## `' . $name . '`';
    $out[] = '';
    if ($t['comment'] !== '') {
        $out[] = $t['comment'];
        $out[] = '';
    }
    $out[] = '```sql';
    foreach ($t['body'] as $bodyLine) {
        $out[] = $bodyLine;
    }
    $out[] = '```';
    $out[] = '';
}

// Foreign keys live in db/schema/foreign_keys.sql (baseline.sql defines tables
// and indexes only), applied after every table exists. Render them so the doc
// stays a complete schema reference.
$fkSql = file_get_contents($root . '/db/schema/foreign_keys.sql');
if (is_string($fkSql)) {
    $fkLines = [];
    foreach (explode("\n", $fkSql) as $fkLine) {
        if (str_starts_with(ltrim($fkLine), '--')) {
            continue;
        }
        $fkLines[] = rtrim($fkLine);
    }
    $out[] = '## Foreign keys';
    $out[] = '';
    $out[] = 'Defined in `db/schema/foreign_keys.sql` (baseline tables carry no'
        . ' inline foreign keys) and applied after every table exists — on a fresh'
        . ' install, a backup restore, or a legacy upgrade.';
    $out[] = '';
    $out[] = '```sql';
    $out[] = trim(implode("\n", $fkLines), "\n");
    $out[] = '```';
    $out[] = '';
}

$generated = implode("\n", $out);
if (!str_ends_with($generated, "\n")) {
    $generated .= "\n";
}

if ($check) {
    $existing = is_file($docPath) ? file_get_contents($docPath) : '';
    if ($existing !== $generated) {
        fwrite(STDERR, "database-schema.md is out of date; run: php bin/generate-schema-doc.php\n");
        exit(1);
    }
    echo "database-schema.md is up to date (" . count($tables) . " tables)\n";
    exit(0);
}

file_put_contents($docPath, $generated);
echo "Wrote $docPath (" . count($tables) . " tables)\n";

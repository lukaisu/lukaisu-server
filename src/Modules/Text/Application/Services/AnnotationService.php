<?php

/**
 * Annotation Service - Annotation management functions.
 *
 * This service contains functions for creating, saving, and managing
 * text annotations for the print/improved view.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0 Migrated from Core/Text/annotation_management.php
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\Services;

use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Utilities\StringUtils;
use Lukaisu\Shared\Infrastructure\Utilities\ErrorHandler;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\UI\Helpers\IconHelper;

/**
 * Service class for annotation management.
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\Services
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */
class AnnotationService
{
    /**
     * Constructor - initialize table prefix.
     */
    public function __construct()
    {
    }

    /**
     * Uses provided annotations, and annotations from database to update annotations.
     *
     * @param int    $textId Id of the text on which to update annotations
     * @param string $oldAnn Old annotations
     *
     * @return string Updated annotations for this text.
     */
    public function recreateSaveAnnotation(int $textId, string $oldAnn): string
    {
        // Get the translations from $oldAnn:
        $oldtrans = array();
        $olditems = preg_split('/[\n]/u', $oldAnn);
        if ($olditems !== false) {
            foreach ($olditems as $olditem) {
                $oldvals = preg_split('/[\t]/u', $olditem);
                if ($oldvals === false) {
                    continue;
                }
                if (count($oldvals) >= 2 && (int)$oldvals[0] > -1) {
                    $trans = '';
                    if (count($oldvals) > 3) {
                        $trans = $oldvals[3];
                    }
                    $oldtrans[$oldvals[0] . "\t" . $oldvals[1]] = $trans;
                }
            }
        }

        // Reset the translations from $oldAnn in $newann and rebuild in $ann:
        $newann = $this->createAnnotation($textId);
        $newitems = preg_split('/[\n]/u', $newann);
        $ann = '';
        if ($newitems === false) {
            return $ann;
        }
        foreach ($newitems as $newitem) {
            $newvals = preg_split('/[\t]/u', $newitem);
            if ($newvals === false) {
                $ann .= $newitem . "\n";
                continue;
            }
            if ((int)$newvals[0] > -1) {
                $key = $newvals[0] . "\t";
                if (isset($newvals[1])) {
                    $key .= $newvals[1];
                }
                if (isset($oldtrans[$key])) {
                    $newvals[3] = $oldtrans[$key];
                }
                $item = implode("\t", $newvals);
            } else {
                $item = $newitem;
            }
            $ann .= $item . "\n";
        }

        QueryBuilder::table('texts')
            ->where('id', '=', $textId)
            ->updatePrepared(['annotated_text' => $ann]);

        return (string)QueryBuilder::table('texts')
            ->where('id', '=', $textId)
            ->valuePrepared('annotated_text');
    }

    /**
     * Create new annotations for a text.
     *
     * @param int $textId Id of the text to create annotations for
     *
     * @return string Annotations for the text
     *
     * @since 2.9.0 Annotations "position" change, they are now equal to position
     *              it was shifted by one index before.
     */
    public function createAnnotation(int $textId): string
    {
        $ann = '';
        $bindings = [$textId];
        $sql = "SELECT
            CASE WHEN word_occurrences.word_count>0 THEN word_occurrences.word_count ELSE 1 END AS Code,
            CASE WHEN CHAR_LENGTH(word_occurrences.text)>0 THEN word_occurrences.text ELSE words.text END AS text,
            word_occurrences.position,
            CASE WHEN word_occurrences.word_count > 0 THEN 0 ELSE 1 END AS TiIsNotWord,
            words.id, words.translation
            FROM (
                word_occurrences
                LEFT JOIN words
                ON word_occurrences.word_id = words.id AND word_occurrences.language_id = words.language_id
            )
            WHERE word_occurrences.text_id = ?"
            . UserScopedQuery::forTablePrepared('word_occurrences', $bindings) . "
            ORDER BY word_occurrences.position ASC, word_occurrences.word_count DESC";

        $until = 0;
        $results = Connection::preparedFetchAll($sql, $bindings);
        // For each term (includes blanks)
        foreach ($results as $record) {
            $actcode = (int)$record['Code'];
            $order = (int)$record['position'];
            if ($order <= $until) {
                continue;
            }
            $savenonterm = '';
            $saveterm = '';
            $savetrans = '';
            $savewordid = '';
            $until = $order;
            if ($record['TiIsNotWord'] != 0) {
                $savenonterm = (string)$record['text'];
            } else {
                $until = $order + 2 * ($actcode - 1);
                $saveterm = (string)$record['text'];
                if (isset($record['id'])) {
                    $savetrans = (string)$record['translation'];
                    $savewordid = (string)$record['id'];
                }
            }
            // Append the annotation
            $ann .= $this->processTerm(
                $savenonterm,
                $saveterm,
                $savetrans,
                $savewordid,
                $order
            );
        }
        return $ann;
    }

    /**
     * Create and save annotations for a text.
     *
     * @param int $textId Text ID
     *
     * @return string Annotations for the text
     */
    public function createSaveAnnotation(int $textId): string
    {
        $ann = $this->createAnnotation($textId);
        QueryBuilder::table('texts')
            ->where('id', '=', $textId)
            ->updatePrepared(['annotated_text' => $ann]);
        return (string)QueryBuilder::table('texts')
            ->where('id', '=', $textId)
            ->valuePrepared('annotated_text');
    }

    /**
     * Process a term for annotation output.
     *
     * @param string $nonterm Non-term text (punctuation, spaces)
     * @param string $term    Term text
     * @param string $trans   Translation
     * @param string $wordid  Word ID
     * @param int    $line    Line/order number
     *
     * @return string Formatted annotation line
     */
    public function processTerm(string $nonterm, string $term, string $trans, string $wordid, int $line): string
    {
        $r = '';
        if ($nonterm != '') {
            $r = "-1\t$nonterm\n";
        }
        if ($term != '') {
            $r .= "$line\t$term\t" . trim($wordid) . "\t" .
            $this->getFirstTranslation($trans) . "\n";
        }
        return $r;
    }

    /**
     * Get the first translation from a translation string.
     *
     * @param string $trans Full translation string (may contain separators)
     *
     * @return string First translation only
     */
    public function getFirstTranslation(string $trans): string
    {
        $arr = preg_split('/[' . StringUtils::getSeparators() . ']/u', $trans);
        if ($arr === false) {
            return '';
        }
        $r = trim($arr[0]);
        if ($r == '*') {
            $r = "";
        }
        return $r;
    }

    /**
     * Get a link to the annotated text if it exists.
     *
     * @param int $textId Text ID
     *
     * @return string HTML link or empty string
     */
    public function getAnnotationLink(int $textId): string
    {
        $row = QueryBuilder::table('texts')
            ->selectRaw('LENGTH(annotated_text) AS text_length')
            ->where('id', '=', $textId)
            ->firstPrepared();
        $length = (int)($row['text_length'] ?? 0);
        if ($length > 0) {
            $icon = IconHelper::render('check', ['title' => 'Annotated Text', 'alt' => 'Annotated Text']);
            return ' &nbsp;<a href="/text/' . $textId .
            '/print" target="_top">' . $icon . '</a>';
        } else {
            return '';
        }
    }

    /**
     * Convert annotations in a JSON format.
     *
     * @param string $ann Annotations.
     *
     * @return string|false A JSON-encoded version of the annotations
     */
    public function annotationToJson(string $ann): string|false
    {
        if ($ann == '') {
            return "{}";
        }
        $arr = array();
        $items = preg_split('/[\n]/u', $ann);
        if ($items === false) {
            return "{}";
        }
        foreach ($items as $item) {
            $vals = preg_split('/[\t]/u', $item);
            if ($vals === false) {
                continue;
            }
            if (count($vals) > 3 && $vals[0] >= 0 && $vals[2] > 0) {
                $arr[intval($vals[0])] = array($vals[1], $vals[2], $vals[3]);
            }
        }
        $json_data = json_encode($arr);
        if ($json_data === false) {
            throw new \RuntimeException("Unable to format annotation data to JSON");
        }
        return $json_data;
    }
}

<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Core\Database;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for inter-table foreign key constraints.
 *
 * These tests verify that:
 * 1. word_id is nullable (can store NULL for unknown words)
 * 2. CASCADE delete works correctly for all FK relationships
 * 3. SET NULL works for word_occurrences.word_id when words are deleted
 * 4. FK constraints prevent orphaned references
 */
#[Group('integration')]
class ForeignKeyTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private static bool $hasForeignKeys = false;

    public static function setUpBeforeClass(): void
    {
        $config = EnvLoader::getDatabaseConfig();
        $testDbname = "test_" . $config['dbname'];

        if (!Globals::getDbConnection()) {
            try {
                $connection = Configuration::connect(
                    $config['server'],
                    $config['userid'],
                    $config['passwd'],
                    $testDbname,
                    $config['socket'] ?? ''
                );
                Globals::setDbConnection($connection);
                self::$dbConnected = true;
            } catch (\Exception $e) {
                self::$dbConnected = false;
            }
        } else {
            self::$dbConnected = true;
        }

        if (self::$dbConnected) {
            // Create a test language for all tests
            Connection::query(
                "INSERT INTO languages (LgName, LgDict1URI, LgCharacterSubstitutions,
                 LgRegexpSplitSentences, LgExceptionsSplitSentences, LgRegexpWordCharacters)
                 VALUES ('FK_Test_Language', 'https://test.com/###', '', '.!?', '', 'a-zA-Z')"
            );
            self::$testLangId = (int) Connection::fetchValue(
                "SELECT LgID FROM languages WHERE LgName = 'FK_Test_Language'",
                'LgID'
            );

            // Check if FK constraints are present by querying INFORMATION_SCHEMA
            $fkCount = (int) Connection::fetchValue(
                "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = '$testDbname'
                 AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                 AND TABLE_NAME = 'word_occurrences'",
                'cnt'
            );
            self::$hasForeignKeys = ($fkCount > 0);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$dbConnected && self::$testLangId > 0) {
            // Cleanup - CASCADE should handle related records
            Connection::query("DELETE FROM languages WHERE LgName LIKE 'FK_Test_%'");
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection not available');
        }
    }

    /**
     * Skip test if FK constraints are not present.
     */
    private function requireForeignKeys(): void
    {
        if (!self::$hasForeignKeys) {
            $this->markTestSkipped('FK constraints not present - run migrations to enable');
        }
    }

    protected function tearDown(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test data after each test
        Connection::query("DELETE FROM word_occurrences WHERE text LIKE 'fktest_%'");
        Connection::query("DELETE FROM sentences WHERE text LIKE 'FK Test%'");
        Connection::query("DELETE FROM texts WHERE TxTitle LIKE 'FK_Test_%'");
        Connection::query("DELETE FROM words WHERE text LIKE 'fktest_%'");
        Connection::query("DELETE FROM word_tag_map WHERE word_id NOT IN (SELECT id FROM words)");
        Connection::query("DELETE FROM tags WHERE text LIKE 'fktest_%'");
        Connection::query("DELETE FROM text_tags WHERE text LIKE 'fktest_%'");
        Connection::query("DELETE FROM news_feeds WHERE name LIKE 'FK_Test_%'");
    }

    // ===== word_id Nullable Tests =====

    /**
     * Test that word_id can be NULL (for unknown words).
     */
    public function testTi2WoIDCanBeNull(): void
    {
        // Create text and sentence
        $textId = $this->createTestText('FK_Test_Nullable');
        $sentenceId = $this->createTestSentence($textId, 'FK Test sentence');

        // Insert text item with NULL word_id
        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES (NULL, " . self::$testLangId . ", $textId, $sentenceId, 1, 1, 'fktest_unknown')"
        );

        // Verify it was inserted with NULL
        $result = Connection::fetchValue(
            "SELECT word_id FROM word_occurrences WHERE text = 'fktest_unknown'",
            'word_id'
        );

        $this->assertNull($result, 'word_id should be NULL for unknown words');
    }

    /**
     * Test that word_id can reference a valid word.
     */
    public function testTi2WoIDCanReferenceWord(): void
    {
        $textId = $this->createTestText('FK_Test_WordRef');
        $sentenceId = $this->createTestSentence($textId, 'FK Test sentence');
        $wordId = $this->createTestWord('fktest_known');

        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES ($wordId, " . self::$testLangId . ", $textId, $sentenceId, 1, 1, 'fktest_known')"
        );

        $result = Connection::fetchValue(
            "SELECT word_id FROM word_occurrences WHERE text = 'fktest_known'",
            'word_id'
        );

        $this->assertEquals($wordId, (int) $result, 'word_id should reference the word');
    }

    // ===== CASCADE Delete Tests =====

    /**
     * Test that deleting a text cascades to sentences.
     */
    public function testTextDeleteCascadesToSentences(): void
    {
        $this->requireForeignKeys();
        $textId = $this->createTestText('FK_Test_SentenceCascade');
        $sentenceId = $this->createTestSentence($textId, 'FK Test cascade sentence');

        // Verify sentence exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE id = $sentenceId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'Sentence should exist before delete');

        // Delete text
        Connection::query("DELETE FROM texts WHERE TxID = $textId");

        // Verify sentence was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE id = $sentenceId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'Sentence should be deleted via CASCADE');
    }

    /**
     * Test that deleting a text cascades to word_occurrences.
     */
    public function testTextDeleteCascadesToTextItems(): void
    {
        $this->requireForeignKeys();
        $textId = $this->createTestText('FK_Test_TextItemCascade');
        $sentenceId = $this->createTestSentence($textId, 'FK Test sentence');

        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES (NULL, " . self::$testLangId . ", $textId, $sentenceId, 1, 1, 'fktest_cascade')"
        );

        // Verify text item exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = $textId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'TextItem should exist before delete');

        // Delete text
        Connection::query("DELETE FROM texts WHERE TxID = $textId");

        // Verify text item was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = $textId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'TextItem should be deleted via CASCADE');
    }

    /**
     * Test that deleting a word sets word_id to NULL (not cascade delete).
     */
    public function testWordDeleteSetsTextItemToNull(): void
    {
        $this->requireForeignKeys();
        $textId = $this->createTestText('FK_Test_SetNull');
        $sentenceId = $this->createTestSentence($textId, 'FK Test sentence');
        $wordId = $this->createTestWord('fktest_setnull');

        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES ($wordId, " . self::$testLangId . ", $textId, $sentenceId, 1, 1, 'fktest_setnull')"
        );

        // Verify word_id is set
        $beforeWoId = Connection::fetchValue(
            "SELECT word_id FROM word_occurrences WHERE text = 'fktest_setnull'",
            'word_id'
        );
        $this->assertEquals($wordId, (int) $beforeWoId, 'word_id should be set before word delete');

        // Delete word
        Connection::query("DELETE FROM words WHERE id = $wordId");

        // Verify word_id is now NULL but text item still exists
        $count = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text = 'fktest_setnull'",
            'cnt'
        );
        $this->assertEquals(1, $count, 'TextItem should still exist after word deletion');

        $afterWoId = Connection::fetchValue(
            "SELECT word_id FROM word_occurrences WHERE text = 'fktest_setnull'",
            'word_id'
        );
        $this->assertNull($afterWoId, 'word_id should be NULL after word deletion');
    }

    /**
     * Test that deleting a word cascades to word_tag_map.
     */
    public function testWordDeleteCascadesToWordTags(): void
    {
        $this->requireForeignKeys();
        $wordId = $this->createTestWord('fktest_tagged');

        Connection::query(
            "INSERT INTO tags (text, comment) VALUES ('fktest_tag', 'Test tag')"
        );
        $tagId = (int) Connection::fetchValue(
            "SELECT id FROM tags WHERE text = 'fktest_tag'",
            'id'
        );

        Connection::query("INSERT INTO word_tag_map (word_id, tag_id) VALUES ($wordId, $tagId)");

        // Verify wordtag exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_tag_map WHERE word_id = $wordId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'Wordtag should exist before delete');

        // Delete word
        Connection::query("DELETE FROM words WHERE id = $wordId");

        // Verify wordtag was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_tag_map WHERE word_id = $wordId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'Wordtag should be deleted via CASCADE');
    }

    /**
     * Test that deleting a tag cascades to word_tag_map.
     */
    public function testTagDeleteCascadesToWordTags(): void
    {
        $this->requireForeignKeys();
        $wordId = $this->createTestWord('fktest_tagged2');

        Connection::query(
            "INSERT INTO tags (text, comment) VALUES ('fktest_tag2', 'Test tag 2')"
        );
        $tagId = (int) Connection::fetchValue(
            "SELECT id FROM tags WHERE text = 'fktest_tag2'",
            'id'
        );

        Connection::query("INSERT INTO word_tag_map (word_id, tag_id) VALUES ($wordId, $tagId)");

        // Verify wordtag exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_tag_map WHERE tag_id = $tagId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'Wordtag should exist before delete');

        // Delete tag
        Connection::query("DELETE FROM tags WHERE id = $tagId");

        // Verify wordtag was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_tag_map WHERE tag_id = $tagId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'Wordtag should be deleted via tag CASCADE');
    }

    /**
     * Test that deleting a text cascades to text_tag_map.
     */
    public function testTextDeleteCascadesToTextTags(): void
    {
        $this->requireForeignKeys();
        $textId = $this->createTestText('FK_Test_TextTag');

        Connection::query(
            "INSERT INTO text_tags (text, comment) VALUES ('fktest_texttag', 'Test text tag')"
        );
        $tagId = (int) Connection::fetchValue(
            "SELECT id FROM text_tags WHERE text = 'fktest_texttag'",
            'id'
        );

        Connection::query("INSERT INTO text_tag_map (text_id, text_tag_id) VALUES ($textId, $tagId)");

        // Verify texttag exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM text_tag_map WHERE text_id = $textId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'Texttag should exist before delete');

        // Delete text
        Connection::query("DELETE FROM texts WHERE TxID = $textId");

        // Verify texttag was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM text_tag_map WHERE text_id = $textId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'Texttag should be deleted via CASCADE');
    }

    /**
     * Test full cascade chain: language -> text -> sentence -> textitem.
     */
    public function testFullCascadeChain(): void
    {
        $this->requireForeignKeys();
        // Create a separate language for this test
        Connection::query(
            "INSERT INTO languages (LgName, LgDict1URI, LgCharacterSubstitutions,
             LgRegexpSplitSentences, LgExceptionsSplitSentences, LgRegexpWordCharacters)
             VALUES ('FK_Test_Cascade_Lang', 'https://test.com/###', '', '.!?', '', 'a-zA-Z')"
        );
        $langId = (int) Connection::fetchValue(
            "SELECT LgID FROM languages WHERE LgName = 'FK_Test_Cascade_Lang'",
            'LgID'
        );

        Connection::query(
            "INSERT INTO texts (TxLgID, TxTitle, TxText, TxAnnotatedText)
             VALUES ($langId, 'FK_Test_FullCascade', 'Test', '')"
        );
        $textId = (int) Connection::fetchValue(
            "SELECT TxID FROM texts WHERE TxTitle = 'FK_Test_FullCascade'",
            'TxID'
        );

        Connection::query(
            "INSERT INTO sentences (language_id, text_id, position, text, first_pos)
             VALUES ($langId, $textId, 1, 'FK Test full cascade', 1)"
        );
        $sentenceId = (int) Connection::fetchValue(
            "SELECT id FROM sentences WHERE text_id = $textId",
            'id'
        );

        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES (NULL, $langId, $textId, $sentenceId, 1, 1, 'fktest_fullcascade')"
        );

        // Verify all exist
        $this->assertEquals(1, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM texts WHERE TxID = $textId",
            'cnt'
        ));
        $this->assertEquals(1, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE id = $sentenceId",
            'cnt'
        ));
        $this->assertEquals(1, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = $textId",
            'cnt'
        ));

        // Delete language - should cascade through entire chain
        Connection::query("DELETE FROM languages WHERE LgID = $langId");

        // Verify all were deleted
        $this->assertEquals(0, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM texts WHERE TxID = $textId",
            'cnt'
        ), 'Text should be deleted via CASCADE');
        $this->assertEquals(0, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE id = $sentenceId",
            'cnt'
        ), 'Sentence should be deleted via CASCADE');
        $this->assertEquals(0, (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = $textId",
            'cnt'
        ), 'TextItem should be deleted via CASCADE');
    }

    /**
     * Test that deleting a newsfeed cascades to feed_links.
     */
    public function testNewsfeedDeleteCascadesToFeedlinks(): void
    {
        $this->requireForeignKeys();
        Connection::query(
            "INSERT INTO news_feeds (language_id, name, source_uri, article_section_tags,
             filter_tags, update_interval, options)
             VALUES (" . self::$testLangId . ", 'FK_Test_Feed', 'https://test.com/feed',
             '', '', 0, '')"
        );
        $feedId = (int) Connection::fetchValue(
            "SELECT id FROM news_feeds WHERE name = 'FK_Test_Feed'",
            'id'
        );

        Connection::query(
            "INSERT INTO feed_links (feed_id, title, link, description, published_at, audio, text)
             VALUES ($feedId, 'FK_Test_Link', 'https://test.com/article', 'Test', NOW(), '', '')"
        );
        $linkId = (int) Connection::fetchValue(
            "SELECT id FROM feed_links WHERE title = 'FK_Test_Link'",
            'id'
        );

        // Verify feedlink exists
        $beforeCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM feed_links WHERE id = $linkId",
            'cnt'
        );
        $this->assertEquals(1, $beforeCount, 'Feedlink should exist before delete');

        // Delete newsfeed
        Connection::query("DELETE FROM news_feeds WHERE id = $feedId");

        // Verify feedlink was cascaded
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM feed_links WHERE id = $linkId",
            'cnt'
        );
        $this->assertEquals(0, $afterCount, 'Feedlink should be deleted via CASCADE');
    }

    /**
     * Test that deleting an archived text (soft delete) preserves tags.
     *
     * Note: Archived texts are now in the texts table with TxArchivedAt set.
     * Tags remain associated since the text still exists.
     */
    public function testArchivedTextPreservesTags(): void
    {
        $this->requireForeignKeys();

        // Create a text with a tag
        Connection::query(
            "INSERT INTO texts (TxLgID, TxTitle, TxText, TxAnnotatedText)
             VALUES (" . self::$testLangId . ", 'FK_Test_Archived', 'Archived content', '')"
        );
        $textId = (int) Connection::fetchValue(
            "SELECT TxID FROM texts WHERE TxTitle = 'FK_Test_Archived'",
            'TxID'
        );

        Connection::query(
            "INSERT INTO text_tags (text, comment) VALUES ('fktest_archtag', 'Arch tag')"
        );
        $tagId = (int) Connection::fetchValue(
            "SELECT id FROM text_tags WHERE text = 'fktest_archtag'",
            'id'
        );

        Connection::query("INSERT INTO text_tag_map (text_id, text_tag_id) VALUES ($textId, $tagId)");

        // Archive the text (soft delete)
        Connection::query("UPDATE texts SET TxArchivedAt = NOW() WHERE TxID = $textId");

        // Verify tag association still exists (text is archived, not deleted)
        $afterCount = (int) Connection::fetchValue(
            "SELECT COUNT(*) AS cnt FROM text_tag_map WHERE text_id = $textId",
            'cnt'
        );
        $this->assertEquals(1, $afterCount, 'TextTag should still exist after archiving');

        // Clean up
        Connection::query("DELETE FROM texts WHERE TxID = $textId");
    }

    // ===== FK Constraint Enforcement Tests =====

    /**
     * Test that inserting a text with invalid language ID fails.
     */
    public function testInvalidLanguageReferenceRejected(): void
    {
        $this->requireForeignKeys();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/foreign key constraint fails/i');

        Connection::query(
            "INSERT INTO texts (TxLgID, TxTitle, TxText, TxAnnotatedText)
             VALUES (255, 'FK_Test_Invalid', 'Test', '')"
        );
    }

    /**
     * Test that inserting a sentence with invalid text ID fails.
     */
    public function testInvalidTextReferenceRejected(): void
    {
        $this->requireForeignKeys();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/foreign key constraint fails/i');

        Connection::query(
            "INSERT INTO sentences (language_id, text_id, position, text, first_pos)
             VALUES (" . self::$testLangId . ", 65535, 1, 'Invalid', 1)"
        );
    }

    /**
     * Test that inserting a textitem with invalid word ID fails.
     */
    public function testInvalidWordReferenceRejected(): void
    {
        $this->requireForeignKeys();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/foreign key constraint fails/i');

        $textId = $this->createTestText('FK_Test_InvalidWord');
        $sentenceId = $this->createTestSentence($textId, 'FK Test sentence');

        Connection::query(
            "INSERT INTO word_occurrences (word_id, language_id, text_id, sentence_id, position, word_count, text)
             VALUES (16777215, " . self::$testLangId . ", $textId, $sentenceId, 1, 1, 'fktest_invalid')"
        );
    }

    // ===== Helper Methods =====

    /**
     * Create a test text and return its ID.
     */
    private function createTestText(string $title): int
    {
        Connection::query(
            "INSERT INTO texts (TxLgID, TxTitle, TxText, TxAnnotatedText)
             VALUES (" . self::$testLangId . ", '$title', 'Test content', '')"
        );
        return (int) Connection::fetchValue(
            "SELECT TxID FROM texts WHERE TxTitle = '$title'",
            'TxID'
        );
    }

    /**
     * Create a test sentence and return its ID.
     */
    private function createTestSentence(int $textId, string $text): int
    {
        Connection::query(
            "INSERT INTO sentences (language_id, text_id, position, text, first_pos)
             VALUES (" . self::$testLangId . ", $textId, 1, '$text', 1)"
        );
        return (int) Connection::fetchValue(
            "SELECT id FROM sentences WHERE text_id = $textId AND text = '$text'",
            'id'
        );
    }

    /**
     * Create a test word and return its ID.
     */
    private function createTestWord(string $text): int
    {
        Connection::query(
            "INSERT INTO words (language_id, text, text_lc, status, translation, word_count)
             VALUES (" . self::$testLangId . ", '$text', '$text', 1, 'test translation', 1)"
        );
        return (int) Connection::fetchValue(
            "SELECT id FROM words WHERE text = '$text'",
            'id'
        );
    }
}

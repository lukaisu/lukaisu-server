<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Services;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Vocabulary\Application\Services\WordListService;
use Lukaisu\Modules\Vocabulary\Application\Services\WordCrudService;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the WordListService class.
 */
class WordListServiceTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;

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
            // Reset auto_increment
            $maxId = Connection::fetchValue("SELECT COALESCE(MAX(LgID), 0) AS value FROM languages");
            Connection::query("ALTER TABLE languages AUTO_INCREMENT = " . ((int)$maxId + 1));

            // Create a test language
            $existingLang = Connection::fetchValue(
                "SELECT LgID AS value FROM languages WHERE LgName = 'WordListTestLang' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                Connection::query(
                    "INSERT INTO languages (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
                    "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, LgExceptionsSplitSentences, " .
                    "LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, LgRightToLeft, LgShowRomanization) " .
                    "VALUES ('WordListTestLang', 'http://test.com/###', '', 'http://translate.test/###', " .
                    "100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1)"
                );
                self::$testLangId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test words and language
        Connection::query("DELETE FROM words WHERE language_id = " . self::$testLangId);
        Connection::query("DELETE FROM languages WHERE LgName = 'WordListTestLang'");

        // Reset auto_increment
        $maxId = Connection::fetchValue("SELECT COALESCE(MAX(LgID), 0) AS value FROM languages");
        Connection::query("ALTER TABLE languages AUTO_INCREMENT = " . ((int)$maxId + 1));
    }

    protected function tearDown(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test words
        Connection::query("DELETE FROM words WHERE text LIKE 'list_test_%'");
    }

    // ===== Constructor and basic tests =====

    public function testServiceCanBeInstantiated(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();

        $this->assertInstanceOf(WordListService::class, $service);
    }

    // ===== Filter condition tests =====

    public function testBuildLangConditionWithLanguageId(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildLangCondition('5');

        $this->assertStringContainsString('language_id=5', $result);
    }

    public function testBuildLangConditionWithEmptyLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildLangCondition('');

        $this->assertEquals('', $result);
    }

    public function testBuildStatusConditionWithStatus(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildStatusCondition('1');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('status', $result);
    }

    public function testBuildStatusConditionWithEmptyStatus(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildStatusCondition('');

        $this->assertEquals('', $result);
    }

    public function testBuildQueryConditionWithTermMode(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildQueryCondition('test', 'term', '');

        $this->assertStringContainsString('text', $result);
        $this->assertStringContainsString('like', $result);
    }

    public function testBuildQueryConditionWithRomanizationMode(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildQueryCondition('test', 'rom', '');

        $this->assertStringContainsString('romanization', $result);
    }

    public function testBuildQueryConditionWithTranslationMode(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildQueryCondition('test', 'transl', '');

        $this->assertStringContainsString('translation', $result);
    }

    public function testBuildQueryConditionWithEmptyQuery(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildQueryCondition('', 'term', '');

        $this->assertEquals('', $result);
    }

    public function testBuildTagConditionWithSingleTag(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildTagCondition('1', '', '');

        $this->assertStringContainsString('having', $result);
        $this->assertStringContainsString('WtTgID', $result);
    }

    public function testBuildTagConditionWithBothTags(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildTagCondition('1', '2', '1');

        $this->assertStringContainsString('having', $result);
        $this->assertStringContainsString('AND', $result);
    }

    public function testBuildTagConditionWithNoTags(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildTagCondition('', '', '');

        $this->assertEquals('', $result);
    }

    public function testBuildTagConditionWithUntaggedFilter(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->buildTagCondition('-1', '', '');

        $this->assertStringContainsString('IS NULL', $result);
    }

    // ===== Word operations tests =====

    public function testCountWordsReturnsZeroForNoMatches(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();

        // Filter for a language with no words
        $count = $service->countWords('', ' and language_id=999999', '', '', '');

        $this->assertEquals(0, $count);
    }

    public function testCountWordsWithExistingWords(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create test words
        $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_count1',
            'status' => 1,
            'translation' => 'count test 1'
        ]);
        $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_count2',
            'status' => 1,
            'translation' => 'count test 2'
        ]);

        $count = $listService->countWords(
            '',
            ' and language_id=' . self::$testLangId,
            '',
            '',
            ''
        );

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testDeleteSingleWord(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word to delete
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_delete',
            'status' => 1,
            'translation' => 'delete test'
        ]);
        $wordId = $result['id'];

        // Delete it (returns void)
        $listService->deleteSingleWord($wordId);

        // Verify it's gone
        $word = $wordService->findById($wordId);
        $this->assertNull($word);
    }

    public function testDeleteByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create words to delete
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $result = $wordService->create([
                'language_id' => self::$testLangId,
                'text' => "list_test_dellist_$i",
                'status' => 1,
                'translation' => "delete list test $i"
            ]);
            $ids[] = $result['id'];
        }

        // Delete by list
        $result = $listService->deleteByIdList($ids);

        // Result is affected row count (should be 3) or success indicator
        $this->assertNotEmpty($result);

        // Verify all are gone
        foreach ($ids as $id) {
            $this->assertNull($wordService->findById($id));
        }
    }

    public function testUpdateStatusByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word with status 1
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_status',
            'status' => 1,
            'translation' => 'status test'
        ]);
        $wordId = $result['id'];

        // Update status to 5
        $message = $listService->updateStatusByIdList([$wordId], 5, false, 's5');

        $this->assertNotEmpty($message);

        // Verify
        $word = $wordService->findById($wordId);
        $this->assertEquals('5', $word['status']);
    }

    public function testUpdateStatusByIdListRelativeIncrement(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word with status 2
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_incr',
            'status' => 2,
            'translation' => 'increment test'
        ]);
        $wordId = $result['id'];

        // Increment status
        $message = $listService->updateStatusByIdList([$wordId], 1, true, 'spl1');

        // Verify status is now 3
        $word = $wordService->findById($wordId);
        $this->assertEquals('3', $word['status']);
    }

    public function testUpdateStatusDateByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_date',
            'status' => 1,
            'translation' => 'date test'
        ]);
        $wordId = $result['id'];

        // Update date
        $message = $listService->updateStatusDateByIdList([$wordId]);

        $this->assertNotEmpty($message);
    }

    public function testDeleteSentencesByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word with sentence
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_sent',
            'status' => 1,
            'translation' => 'sentence test',
            'sentence' => 'This is a {list_test_sent} sentence.'
        ]);
        $wordId = $result['id'];

        // Delete sentences
        $message = $listService->deleteSentencesByIdList([$wordId]);

        $this->assertNotEmpty($message);

        // Verify sentence is null
        $word = $wordService->findById($wordId);
        $this->assertNull($word['sentence']);
    }

    public function testToLowercaseByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word with mixed case
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'List_Test_Case',
            'status' => 1,
            'translation' => 'case test'
        ]);
        $wordId = $result['id'];

        // Convert to lowercase
        $message = $listService->toLowercaseByIdList([$wordId]);

        $this->assertNotEmpty($message);

        // Verify text is now lowercase
        $word = $wordService->findById($wordId);
        $this->assertEquals('list_test_case', $word['text']);
    }

    public function testCapitalizeByIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word in lowercase
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_cap',
            'status' => 1,
            'translation' => 'capitalize test'
        ]);
        $wordId = $result['id'];

        // Capitalize
        $message = $listService->capitalizeByIdList([$wordId]);

        $this->assertNotEmpty($message);

        // Verify text is capitalized
        $word = $wordService->findById($wordId);
        $this->assertEquals('List_test_cap', $word['text']);
    }

    // ===== Regex validation tests =====

    public function testValidateRegexPatternWithValidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->validateRegexPattern('^test');

        $this->assertTrue($result);
    }

    public function testValidateRegexPatternWithInvalidPattern(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->validateRegexPattern('[invalid');

        $this->assertFalse($result);
    }

    // ===== Form data tests =====

    public function testGetNewTermFormData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getNewTermFormData(self::$testLangId);

        $this->assertArrayHasKey('showRoman', $result);
        $this->assertArrayHasKey('scrdir', $result);
        $this->assertTrue($result['showRoman']); // Our test lang has LgShowRomanization=1
    }

    public function testGetEditFormDataWithExistingWord(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create a word
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_edit',
            'status' => 2,
            'translation' => 'edit test'
        ]);
        $wordId = $result['id'];

        $formData = $listService->getEditFormData($wordId);

        $this->assertNotNull($formData);
        $this->assertEquals($wordId, $formData['id']);
        $this->assertEquals(self::$testLangId, $formData['language_id']);
        $this->assertEquals('list_test_edit', $formData['text']);
        $this->assertEquals('edit test', $formData['translation']);
        $this->assertEquals(2, $formData['status']);
    }

    public function testGetEditFormDataWithNonExistentWord(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getEditFormData(999999);

        $this->assertNull($result);
    }

    // ===== Export SQL tests =====

    public function testGetAnkiExportSqlWithIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getAnkiExportSql([1, 2, 3], '', '', '', '', '');

        $this->assertIsArray($result);
        $this->assertStringContainsString('id', $result['sql']);
        $this->assertStringContainsString('translation', $result['sql']);
        $this->assertStringContainsString('?', $result['sql']);
        $this->assertSame([1, 2, 3], $result['params']);
    }

    public function testGetTsvExportSqlWithIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getTsvExportSql([1, 2, 3], '', '', '', '', '');

        $this->assertIsArray($result);
        $this->assertStringContainsString('status', $result['sql']);
        $this->assertStringContainsString('?', $result['sql']);
        $this->assertSame([1, 2, 3], $result['params']);
    }

    public function testGetFlexibleExportSqlWithIdList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getFlexibleExportSql([1, 2, 3], '', '', '', '', '');

        $this->assertIsArray($result);
        $this->assertStringContainsString('LgExportTemplate', $result['sql']);
        $this->assertStringContainsString('?', $result['sql']);
        $this->assertSame([1, 2, 3], $result['params']);
    }

    public function testGetTestWordIdsSql(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new WordListService();
        $result = $service->getTestWordIdsSql('', '', '', '', '');

        $this->assertIsArray($result);
        $this->assertStringContainsString('id', $result['sql']);
        $this->assertStringContainsString('words', $result['sql']);
        $this->assertEmpty($result['params']);
    }

    // ===== Get filtered word IDs =====

    public function testGetFilteredWordIds(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create test words
        $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_filter1',
            'status' => 1,
            'translation' => 'filter test 1'
        ]);
        $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_filter2',
            'status' => 1,
            'translation' => 'filter test 2'
        ]);

        $ids = $listService->getFilteredWordIds(
            '',
            ' and language_id=' . self::$testLangId,
            '',
            '',
            ''
        );

        $this->assertIsArray($ids);
        $this->assertGreaterThanOrEqual(2, count($ids));
    }

    // ===== Words list tests =====

    public function testGetWordsList(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create test word
        $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_listword',
            'status' => 1,
            'translation' => 'list word test'
        ]);

        $filters = [
            'whLang' => ' and language_id=' . self::$testLangId,
            'whStat' => '',
            'whQuery' => '',
            'whTag' => '',
            'textId' => ''
        ];

        $result = $listService->getWordsList($filters, 1, 1, 10);

        $this->assertIsArray($result);

        $found = false;
        foreach ($result as $record) {
            if ($record['text'] === 'list_test_listword') {
                $found = true;
                $this->assertEquals('list word test', $record['translation']);
            }
        }

        $this->assertTrue($found);
    }

    // ===== Save/update tests =====

    public function testSaveNewWord(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        $data = [
            'language_id' => self::$testLangId,
            'text' => 'list_test_save',
            'status' => 1,
            'translation' => 'save test',
            'sentence' => '',
            'romanization' => ''
        ];

        $wordId = $listService->saveNewWord($data);

        // Returns the word ID
        $this->assertIsInt($wordId);
        $this->assertGreaterThan(0, $wordId);

        // Verify word exists
        $word = $wordService->findByText('list_test_save', self::$testLangId);
        $this->assertNotNull($word);
    }

    public function testUpdateWord(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $wordService = new WordCrudService();
        $listService = new WordListService();

        // Create word first
        $result = $wordService->create([
            'language_id' => self::$testLangId,
            'text' => 'list_test_update',
            'status' => 1,
            'translation' => 'original'
        ]);
        $wordId = $result['id'];

        // Update it
        $data = [
            'id' => $wordId,
            'text' => 'list_test_update',
            'status' => 3,
            'WoOldStatus' => 1,
            'translation' => 'updated',
            'sentence' => 'New sentence',
            'romanization' => 'roman'
        ];

        $result = $listService->updateWord($data);

        // Result is affected row count (should be 1) or success indicator
        $this->assertNotEmpty($result);

        // Verify changes
        $word = $wordService->findById($wordId);
        $this->assertEquals('3', $word['status']);
        $this->assertEquals('updated', $word['translation']);
    }
}

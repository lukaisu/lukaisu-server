<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Services;

use Lukaisu\Modules\Language\Domain\Language;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the LanguageFacade class.
 *
 * Tests language CRUD operations, text reparsing,
 * and related data count functionality.
 */
#[Group('integration')]
class LanguageServiceTest extends TestCase
{
    private static bool $dbConnected = false;
    private LanguageFacade $service;
    private static array $testLanguageIds = [];

    /** @var array<string, mixed> Original $_REQUEST for cleanup */
    private array $originalRequest;

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
    }

    protected function setUp(): void
    {
        $this->originalRequest = $_REQUEST;
        $_REQUEST = [];
        $this->service = new LanguageFacade();
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;

        if (!self::$dbConnected) {
            return;
        }

        // Clean up test languages after each test
        Connection::query("DELETE FROM languages WHERE name LIKE 'Test_%'");
        Connection::query("DELETE FROM languages WHERE name LIKE 'TestLang%'");
        self::$testLanguageIds = [];
    }

    /**
     * Set language data in $_REQUEST for testing create/update.
     *
     * @param array<string, mixed> $data Language data
     */
    private function setLanguageRequestData(array $data): void
    {
        $_REQUEST = $data;
    }

    /**
     * Helper to create a test language directly in the database.
     *
     * @param string $name Language name
     *
     * @return int The created language ID
     */
    private function createTestLanguage(string $name): int
    {
        Connection::query(
            "INSERT INTO languages (
                name, dict1_uri, dict2_uri, google_translate_uri,
                text_size, regexp_split_sentences, regexp_word_characters,
                remove_spaces, split_each_char, right_to_left, show_romanization
            ) VALUES (
                '$name', 'https://dict.test/lukaisu_term', '', 'https://translate.test/lukaisu_term',
                100, '.!?', 'a-zA-Z',
                0, 0, 0, 1
            )"
        );
        $id = (int) mysqli_insert_id(Globals::getDbConnection());
        self::$testLanguageIds[] = $id;
        return $id;
    }

    // ===== getAllLanguages() tests =====

    public function testGetAllLanguagesReturnsArray(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getAllLanguages();
        $this->assertIsArray($result);
    }

    public function testGetAllLanguagesReturnsNameToIdMapping(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a test language
        $this->createTestLanguage('TestLang_GetAll');

        $result = $this->service->getAllLanguages();

        $this->assertArrayHasKey('TestLang_GetAll', $result);
        $this->assertIsInt($result['TestLang_GetAll']);
    }

    public function testGetAllLanguagesExcludesEmptyNames(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Clean up any existing empty-name language first
        Connection::query(
            "DELETE FROM languages WHERE name = ''"
        );

        // Insert language with empty name (placeholder)
        Connection::query(
            "INSERT INTO languages (name, dict1_uri, text_size, regexp_split_sentences, regexp_word_characters)
             VALUES ('', 'https://test.com', 100, '.!?', 'a-z')"
        );

        $result = $this->service->getAllLanguages();

        $this->assertArrayNotHasKey('', $result);

        // Clean up
        Connection::query(
            "DELETE FROM languages WHERE name = ''"
        );
    }

    // ===== getById() tests =====

    public function testGetByIdReturnsLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_GetById');

        $result = $this->service->getById($id);

        $this->assertInstanceOf(Language::class, $result);
        $this->assertEquals($id, $result->id()->toInt());
        $this->assertEquals('TestLang_GetById', $result->name());
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getById(999999);

        $this->assertNull($result);
    }

    public function testGetByIdReturnsEmptyLanguageForZero(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getById(0);

        $this->assertInstanceOf(Language::class, $result);
        $this->assertTrue($result->id()->isNew());
        $this->assertEquals('New Language', $result->name());
    }

    public function testGetByIdReturnsEmptyLanguageForNegative(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getById(-1);

        $this->assertInstanceOf(Language::class, $result);
        $this->assertTrue($result->id()->isNew());
    }

    public function testGetByIdPopulatesAllFields(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_AllFields');

        $result = $this->service->getById($id);

        $this->assertEquals('https://dict.test/lukaisu_term', $result->dict1Uri());
        $this->assertEquals('', $result->dict2Uri());
        $this->assertEquals('https://translate.test/lukaisu_term', $result->translatorUri());
        $this->assertEquals(100, $result->textSize());
        $this->assertEquals('.!?', $result->regexpSplitSentences());
        $this->assertEquals('a-zA-Z', $result->regexpWordCharacters());
        $this->assertFalse($result->removeSpaces());
        $this->assertFalse($result->splitEachChar());
        $this->assertFalse($result->rightToLeft());
        $this->assertTrue($result->showRomanization());
    }

    // ===== createEmptyLanguage() tests =====

    public function testCreateEmptyLanguageReturnsLanguageObject(): void
    {
        $result = $this->service->createEmptyLanguage();

        $this->assertInstanceOf(Language::class, $result);
    }

    public function testCreateEmptyLanguageHasDefaultValues(): void
    {
        $result = $this->service->createEmptyLanguage();

        $this->assertTrue($result->id()->isNew());
        $this->assertEquals('New Language', $result->name());
        $this->assertEquals('', $result->dict1Uri());
        $this->assertEquals('', $result->dict2Uri());
        $this->assertEquals('', $result->translatorUri());
        $this->assertEquals(100, $result->textSize());
        $this->assertEquals('', $result->characterSubstitutions());
        $this->assertEquals('.!?', $result->regexpSplitSentences());
        $this->assertEquals('', $result->exceptionsSplitSentences());
        $this->assertEquals('a-zA-Z', $result->regexpWordCharacters());
        $this->assertFalse($result->removeSpaces());
        $this->assertFalse($result->splitEachChar());
        $this->assertFalse($result->rightToLeft());
        $this->assertEquals('', $result->ttsVoiceApi());
        $this->assertTrue($result->showRomanization());
    }

    // ===== exists() tests =====

    public function testExistsReturnsTrueForExistingLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_Exists');

        $result = $this->service->exists($id);

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseForNonExistentLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->exists(999999);

        $this->assertFalse($result);
    }

    // ===== create() tests =====

    public function testCreateSavesLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->setLanguageRequestData([
            'name' => 'TestLang_Create',
            'dict1_uri' => 'https://dict.test/lukaisu_term',
            'dict2_uri' => '',
            'google_translate_uri' => 'https://translate.test/lukaisu_term',
            'export_template' => '',
            'text_size' => '150',
            'character_substitutions' => '',
            'regexp_split_sentences' => '.!?',
            'exceptions_split_sentences' => '',
            'regexp_word_characters' => 'a-zA-Z',
            'tts_voice_api' => '',
        ]);

        $result = $this->service->create();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);

        // Verify language was created
        $languages = $this->service->getAllLanguages();
        $this->assertArrayHasKey('TestLang_Create', $languages);
    }

    public function testCreateWithCheckboxOptions(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->setLanguageRequestData([
            'name' => 'TestLang_Checkboxes',
            'dict1_uri' => 'https://dict.test/lukaisu_term',
            'dict2_uri' => '',
            'google_translate_uri' => '',
            'export_template' => '',
            'text_size' => '100',
            'character_substitutions' => '',
            'regexp_split_sentences' => '.!?',
            'exceptions_split_sentences' => '',
            'regexp_word_characters' => 'a-zA-Z',
            'tts_voice_api' => '',
            'remove_spaces' => '1',
            'split_each_char' => '1',
            'right_to_left' => '1',
            'show_romanization' => '1',
        ]);

        $this->service->create();

        $languages = $this->service->getAllLanguages();
        $id = $languages['TestLang_Checkboxes'];
        $lang = $this->service->getById($id);

        $this->assertTrue($lang->removeSpaces());
        $this->assertTrue($lang->splitEachChar());
        $this->assertTrue($lang->rightToLeft());
        $this->assertTrue($lang->showRomanization());
    }

    // ===== update() tests =====

    public function testUpdateModifiesLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_Update');

        $this->setLanguageRequestData([
            'name' => 'TestLang_Updated',
            'dict1_uri' => 'https://newdict.test/lukaisu_term',
            'dict2_uri' => 'https://dict2.test/lukaisu_term',
            'google_translate_uri' => 'https://newtranslate.test/lukaisu_term',
            'export_template' => 'template',
            'text_size' => '200',
            'character_substitutions' => 'a=b',
            'regexp_split_sentences' => '.!?:',
            'exceptions_split_sentences' => 'Mr.',
            'regexp_word_characters' => 'a-zA-Z0-9',
            'tts_voice_api' => '{"input": "lukaisu_term"}',
        ]);

        $result = $this->service->update($id);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        $lang = $this->service->getById($id);
        $this->assertEquals('TestLang_Updated', $lang->name());
        $this->assertEquals('https://newdict.test/lukaisu_term', $lang->dict1Uri());
        $this->assertEquals(200, $lang->textSize());
    }

    public function testUpdateReturnsErrorForNonExistentLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->setLanguageRequestData([
            'name' => 'TestLang_NotFound',
            'dict1_uri' => '',
            'dict2_uri' => '',
            'google_translate_uri' => '',
            'export_template' => '',
            'text_size' => '100',
            'character_substitutions' => '',
            'regexp_split_sentences' => '.!?',
            'exceptions_split_sentences' => '',
            'regexp_word_characters' => 'a-z',
            'tts_voice_api' => '',
        ]);

        $result = $this->service->update(999999);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Cannot access language data', $result['error']);
    }

    public function testUpdateIndicatesReparsingNotNeeded(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_NoReparse');

        // Update only non-parsing-related fields
        $this->setLanguageRequestData([
            'name' => 'TestLang_NoReparse_Updated',
            'dict1_uri' => 'https://newdict.test/lukaisu_term',
            'dict2_uri' => '',
            'google_translate_uri' => '',
            'export_template' => '',
            'text_size' => '150',
            'character_substitutions' => '',
            'regexp_split_sentences' => '.!?',
            'exceptions_split_sentences' => '',
            'regexp_word_characters' => 'a-zA-Z',
            'tts_voice_api' => '',
        ]);

        $result = $this->service->update($id);

        // When no parsing-related fields change, reparsed count should be null or 0
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        // reparsed is null when no texts need reparsing
        $this->assertTrue($result['reparsed'] === null || $result['reparsed'] === 0);
    }

    // ===== delete() tests =====

    public function testDeleteRemovesLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_Delete');

        $result = $this->service->delete($id);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertFalse($this->service->exists($id));
    }

    public function testDeleteFailsWithRelatedTexts(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_WithTexts');

        // Add a related text
        Connection::query(
            "INSERT INTO texts (language_id, title, text, audio_uri)
             VALUES ($id, 'Test Text', 'Test content', '')"
        );

        $result = $this->service->delete($id);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('must first delete', $result['error']);
        $this->assertTrue($this->service->exists($id));

        // Cleanup
        Connection::query("DELETE FROM texts WHERE language_id = $id");
    }

    public function testDeleteFailsWithRelatedWords(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_WithWords');

        // Add a related word
        Connection::query(
            "INSERT INTO words (language_id, text, text_lc, status, word_count, status_changed_at)
             VALUES ($id, 'test', 'test', 1, 1, NOW())"
        );

        $result = $this->service->delete($id);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('must first delete', $result['error']);
        $this->assertTrue($this->service->exists($id));

        // Cleanup
        Connection::query("DELETE FROM words WHERE language_id = $id");
    }

    // ===== getRelatedDataCounts() tests =====

    public function testGetRelatedDataCountsReturnsZerosForNewLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_NoCounts');

        $result = $this->service->getRelatedDataCounts($id);

        $this->assertEquals(0, $result['texts']);
        $this->assertEquals(0, $result['archivedTexts']);
        $this->assertEquals(0, $result['words']);
        $this->assertEquals(0, $result['feeds']);
    }

    public function testGetRelatedDataCountsReturnsCorrectCounts(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_WithData');

        // Add texts
        Connection::query(
            "INSERT INTO texts (language_id, title, text, audio_uri)
             VALUES ($id, 'Text 1', 'Content 1', ''), ($id, 'Text 2', 'Content 2', '')"
        );

        // Add a word
        Connection::query(
            "INSERT INTO words (language_id, text, text_lc, status, word_count, status_changed_at)
             VALUES ($id, 'word', 'word', 1, 1, NOW())"
        );

        $result = $this->service->getRelatedDataCounts($id);

        $this->assertEquals(2, $result['texts']);
        $this->assertEquals(1, $result['words']);
        $this->assertEquals(0, $result['archivedTexts']);
        $this->assertEquals(0, $result['feeds']);

        // Cleanup
        Connection::query("DELETE FROM texts WHERE language_id = $id");
        Connection::query("DELETE FROM words WHERE language_id = $id");
    }

    // ===== canDelete() tests =====

    public function testCanDeleteReturnsTrueForNewLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_CanDelete');

        $result = $this->service->canDelete($id);

        $this->assertTrue($result);
    }

    public function testCanDeleteReturnsFalseWithRelatedData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_CannotDelete');

        // Add a related text
        Connection::query(
            "INSERT INTO texts (language_id, title, text, audio_uri)
             VALUES ($id, 'Test', 'Content', '')"
        );

        $result = $this->service->canDelete($id);

        $this->assertFalse($result);

        // Cleanup
        Connection::query("DELETE FROM texts WHERE language_id = $id");
    }

    // ===== isDuplicateName() tests =====

    public function testIsDuplicateNameReturnsTrueForDuplicate(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->createTestLanguage('TestLang_Duplicate');

        $result = $this->service->isDuplicateName('TestLang_Duplicate');

        $this->assertTrue($result);
    }

    public function testIsDuplicateNameReturnsFalseForUnique(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->isDuplicateName('NonExistentLanguage123');

        $this->assertFalse($result);
    }

    public function testIsDuplicateNameExcludesCurrentLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_SameName');

        // Should not be duplicate when excluding its own ID
        $result = $this->service->isDuplicateName('TestLang_SameName', $id);

        $this->assertFalse($result);
    }

    // ===== getLanguagesWithStats() tests =====

    public function testGetLanguagesWithStatsReturnsArray(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguagesWithStats();

        $this->assertIsArray($result);
    }

    public function testGetLanguagesWithStatsIncludesExpectedFields(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $this->createTestLanguage('TestLang_Stats');

        $result = $this->service->getLanguagesWithStats();

        // Find our test language
        $testLang = null;
        foreach ($result as $lang) {
            if ($lang['name'] === 'TestLang_Stats') {
                $testLang = $lang;
                break;
            }
        }

        $this->assertNotNull($testLang);
        $this->assertArrayHasKey('id', $testLang);
        $this->assertArrayHasKey('name', $testLang);
        $this->assertArrayHasKey('hasExportTemplate', $testLang);
        $this->assertArrayHasKey('textCount', $testLang);
        $this->assertArrayHasKey('archivedTextCount', $testLang);
        $this->assertArrayHasKey('wordCount', $testLang);
        $this->assertArrayHasKey('feedCount', $testLang);
        $this->assertArrayHasKey('articleCount', $testLang);
    }

    public function testGetLanguagesWithStatsExcludesEmptyNames(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // First create a valid test language to ensure we have at least one result
        $this->createTestLanguage('TestLang_StatsNotEmpty');

        // Insert language with empty name
        Connection::query(
            "INSERT INTO languages (name, dict1_uri, text_size, regexp_split_sentences, regexp_word_characters)
             VALUES ('', 'https://test.com', 100, '.!?', 'a-z')"
        );

        $result = $this->service->getLanguagesWithStats();

        // Ensure we have results to check
        $this->assertNotEmpty($result);

        foreach ($result as $lang) {
            $this->assertNotEquals('', $lang['name']);
        }
    }

    // ===== refresh() tests =====

    public function testRefreshReturnsMessage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $id = $this->createTestLanguage('TestLang_Refresh');

        $result = $this->service->refresh($id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sentencesDeleted', $result);
        $this->assertArrayHasKey('textItemsDeleted', $result);
        $this->assertArrayHasKey('sentencesAdded', $result);
        $this->assertArrayHasKey('textItemsAdded', $result);
    }
}

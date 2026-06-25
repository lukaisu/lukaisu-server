<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Vocabulary\Services;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Vocabulary\Application\Services\WordContextService;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the WordContextService class.
 *
 * Tests language/text context operations.
 */
class WordContextServiceTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private WordContextService $service;

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
            // Create a test language if it doesn't exist
            $existingLang = Connection::fetchValue(
                "SELECT id AS value FROM " . Globals::table('languages') . " WHERE name = 'TestLanguage' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                Connection::query(
                    "INSERT INTO " . Globals::table('languages') .
                    " (name, dict1_uri, dict2_uri, google_translate_uri, " .
                    "text_size, character_substitutions, regexp_split_sentences, exceptions_split_sentences, " .
                    "regexp_word_characters, remove_spaces, split_each_char, right_to_left, show_romanization) " .
                    "VALUES ('TestLanguage', 'http://test.com/###', '', 'http://translate.test/###', " .
                    "100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1)"
                );
                self::$testLangId = (int)Connection::fetchValue(
                    "SELECT LAST_INSERT_ID() AS value"
                );
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test language
        Connection::query("DELETE FROM " . Globals::table('languages') . " WHERE id = " . self::$testLangId);
    }

    protected function setUp(): void
    {
        $this->service = new WordContextService();
    }

    // ===== getLanguageData() tests =====

    public function testGetLanguageDataReturnsCorrectData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $langData = $this->service->getLanguageData(self::$testLangId);

        $this->assertIsArray($langData);
        $this->assertArrayHasKey('showRoman', $langData);
        $this->assertArrayHasKey('translateUri', $langData);
        $this->assertArrayHasKey('name', $langData);

        $this->assertTrue($langData['showRoman']); // We set show_romanization = 1
        $this->assertEquals('TestLanguage', $langData['name']);
        $this->assertStringContainsString('translate.test', $langData['translateUri']);
    }

    // ===== textToClassName() tests =====

    public function testTextToClassNameConvertsText(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $className = $this->service->textToClassName('hello');

        // Should return a hex-like string
        $this->assertIsString($className);
        $this->assertNotEmpty($className);
    }

    public function testTextToClassNameHandlesSpecialCharacters(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $className = $this->service->textToClassName('héllo wörld');

        $this->assertIsString($className);
        $this->assertNotEmpty($className);
    }

    // ===== getLanguageDictionaries() tests =====

    public function testGetLanguageDictionariesReturnsEmptyForNonExistentText(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageDictionaries(999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dict1', $result);
        $this->assertArrayHasKey('dict2', $result);
        $this->assertArrayHasKey('translate', $result);
    }

    // ===== exportTermAsJson() tests =====

    public function testExportTermAsJsonReturnsValidJson(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $json = $this->service->exportTermAsJson(
            123,
            'test term',
            'test roman',
            'test translation',
            2
        );

        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(123, $decoded['woid']);
        $this->assertEquals('test term', $decoded['text']);
        $this->assertEquals('test roman', $decoded['romanization']);
        $this->assertEquals('test translation', $decoded['translation']);
        $this->assertEquals(2, $decoded['status']);
    }

    // ===== getLanguageIdFromText() tests =====

    public function testGetLanguageIdFromTextReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageIdFromText(999999);
        $this->assertNull($result);
    }
}

<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Controllers;

use Lukaisu\Modules\Text\Http\TextController;
use Lukaisu\Modules\Text\Http\TextReadController;
use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the TextController::read() method and related functionality.
 *
 * Tests controller initialization, service integration,
 * and verifies the MVC pattern implementation for text reading.
 */
class TextControllerReadTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private static int $testTextId = 0;
    private static int $testText2Id = 0;
    private array $originalRequest;
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;

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
            // Create a test language
            $existingLang = Connection::fetchValue(
                "SELECT id AS value FROM " . Globals::table('languages') .
                " WHERE name = 'ReadControllerTestLang' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                $sql = "INSERT INTO " . Globals::table('languages') .
                    " (name, dict1_uri, dict2_uri, google_translate_uri, " .
                    "text_size, character_substitutions, regexp_split_sentences, " .
                    "exceptions_split_sentences, regexp_word_characters, remove_spaces, " .
                    "split_each_char, right_to_left, show_romanization, tts_voice_api) " .
                    "VALUES ('ReadControllerTestLang', 'http://dict1.test/###', " .
                    "'http://dict2.test/###', 'http://translate.test/?sl=en&tl=fr&text=###', " .
                    "120, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1, 'Google')";
                Connection::query($sql);
                self::$testLangId = (int)Connection::fetchValue(
                    "SELECT LAST_INSERT_ID() AS value"
                );
            }

            // Create first test text
            $annotatedText = "-1\t.\n0\tTest\t\t*\n0\ttext\t\ttranslation";
            Connection::query(
                "INSERT INTO " . Globals::table('texts') . " (language_id, title, text, annotated_text, audio_uri, " .
                "source_uri, audio_position, position) " .
                "VALUES (" . self::$testLangId . ", 'ReadControllerTestText', 'Test text.', " .
                "'" . mysqli_real_escape_string(Globals::getDbConnection(), $annotatedText) . "', " .
                "'http://audio.test/audio.mp3', 'http://source.test/article', 15, 50)"
            );
            self::$testTextId = (int)Connection::fetchValue(
                "SELECT LAST_INSERT_ID() AS value"
            );

            // Create second test text (for navigation tests)
            Connection::query(
                "INSERT INTO " . Globals::table('texts') . " (language_id, title, text, annotated_text) " .
                "VALUES (" . self::$testLangId . ", 'ReadControllerTestText2', 'Second test.', '')"
            );
            self::$testText2Id = (int)Connection::fetchValue(
                "SELECT LAST_INSERT_ID() AS value"
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbConnected) {
            return;
        }

        // Clean up test data
        $textIds = self::$testTextId . ", " . self::$testText2Id;
        Connection::query(
            "DELETE FROM " . Globals::table('word_occurrences') .
            " WHERE text_id IN (" . $textIds . ")"
        );
        Connection::query(
            "DELETE FROM " . Globals::table('sentences') .
            " WHERE text_id IN (" . $textIds . ")"
        );
        Connection::query(
            "DELETE FROM " . Globals::table('texts') .
            " WHERE id IN (" . $textIds . ")"
        );
        Connection::query(
            "DELETE FROM " . Globals::table('languages') .
            " WHERE name = 'ReadControllerTestLang'"
        );
    }

    protected function setUp(): void
    {
        // Save original superglobals
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;

        // Reset superglobals
        $_REQUEST = [];
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        // Restore superglobals
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    // ===== Constructor tests =====

    public function testControllerCanBeInstantiated(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $controller = new TextController(
            new \Lukaisu\Modules\Text\Application\TextFacade(),
            new \Lukaisu\Modules\Language\Application\LanguageFacade()
        );

        $this->assertInstanceOf(TextController::class, $controller);
    }

    // ===== Method existence tests =====

    public function testControllerHasReadMethod(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $controller = new TextController(
            new \Lukaisu\Modules\Text\Application\TextFacade(),
            new \Lukaisu\Modules\Language\Application\LanguageFacade()
        );

        $this->assertTrue(method_exists($controller, 'read'));
    }

    // ===== Service integration tests =====

    public function testTextServiceCanRetrieveTestTextData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextFacade();

        // Test getTextForReading
        $headerData = $service->getTextForReading(self::$testTextId);
        $this->assertIsArray($headerData);
        $this->assertEquals('ReadControllerTestText', $headerData['title']);
        $this->assertEquals(self::$testLangId, (int)$headerData['language_id']);
        $this->assertEquals('http://audio.test/audio.mp3', $headerData['audio_uri']);
        $this->assertEquals(15, (int)$headerData['audio_position']);

        // Test getTextDataForContent
        $contentData = $service->getTextDataForContent(self::$testTextId);
        $this->assertIsArray($contentData);
        $this->assertEquals(50, (int)$contentData['position']);
        $this->assertStringContainsString('Test', $contentData['annotated_text']);

        // Test getLanguageSettingsForReading
        $langSettings = $service->getLanguageSettingsForReading(self::$testLangId);
        $this->assertIsArray($langSettings);
        $this->assertEquals(120, (int)$langSettings['text_size']);
        $this->assertEquals('http://dict1.test/###', $langSettings['dict1_uri']);

        // Test getTtsVoiceApi
        $voiceApi = $service->getTtsVoiceApi(self::$testLangId);
        $this->assertEquals('Google', $voiceApi);
    }

    // ===== Request parameter extraction tests =====

    public function testGetTextIdFromRequestWithTextParam(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = (string)self::$testTextId;

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        $this->assertEquals(self::$testTextId, $result);
    }

    public function testGetTextIdFromRequestWithStartParam(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['start'] = (string)self::$testTextId;

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        $this->assertEquals(self::$testTextId, $result);
    }

    public function testGetTextIdFromRequestPrefersTextOverStart(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = (string)self::$testTextId;
        $_REQUEST['start'] = (string)self::$testText2Id;

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        // Should prefer 'text' over 'start'
        $this->assertEquals(self::$testTextId, $result);
    }

    public function testGetTextIdFromRequestReturnsNullForMissingParams(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // No text or start parameter set

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        $this->assertNull($result);
    }

    public function testGetTextIdFromRequestReturnsNullForNonNumeric(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = 'not-a-number';

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        $this->assertNull($result);
    }

    public function testGetTextIdFromRequestHandlesZero(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = '0';

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        // '0' is numeric, so it should return 0
        $this->assertEquals(0, $result);
    }

    // ===== Settings tests =====

    public function testSettingsAreRetrievedCorrectly(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Test that Settings class can retrieve values
        $showAllWords = Settings::getZeroOrOne('showallwords', 1);
        $this->assertContains($showAllWords, [0, 1]);

        $showLearningTrans = Settings::getZeroOrOne('showlearningtranslations', 1);
        $this->assertContains($showLearningTrans, [0, 1]);
    }

    // ===== Integration tests =====

    public function testFullReadingDataRetrieval(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextFacade();

        // Simulate what the controller does
        $textId = self::$testTextId;

        // Step 1: Get header data
        $headerData = $service->getTextForReading($textId);
        $this->assertNotNull($headerData);

        $title = (string) $headerData['title'];
        $langId = (int) $headerData['language_id'];
        $media = isset($headerData['audio_uri']) ? trim((string) $headerData['audio_uri']) : '';
        $audioPosition = (int) ($headerData['audio_position'] ?? 0);
        $sourceUri = (string) ($headerData['source_uri'] ?? '');
        $text = (string) $headerData['text'];
        $languageName = (string) $headerData['name'];

        $this->assertEquals('ReadControllerTestText', $title);
        $this->assertEquals(self::$testLangId, $langId);
        $this->assertEquals('http://audio.test/audio.mp3', $media);
        $this->assertEquals(15, $audioPosition);
        $this->assertEquals('http://source.test/article', $sourceUri);
        $this->assertEquals('Test text.', $text);
        $this->assertEquals('ReadControllerTestLang', $languageName);

        // Step 2: Get text content data
        $textData = $service->getTextDataForContent($textId);
        $this->assertNotNull($textData);

        $annotatedText = (string) ($textData['annotated_text'] ?? '');
        $textPosition = (int) ($textData['position'] ?? 0);

        $this->assertStringContainsString('Test', $annotatedText);
        $this->assertEquals(50, $textPosition);

        // Step 3: Get language settings
        $langSettings = $service->getLanguageSettingsForReading($langId);
        $this->assertNotNull($langSettings);

        $dictLink1 = $langSettings['dict1_uri'] ?? '';
        $dictLink2 = $langSettings['dict2_uri'] ?? '';
        $translatorLink = $langSettings['google_translate_uri'] ?? '';
        $textSize = (int) $langSettings['text_size'];
        $regexpWordChars = $langSettings['regexp_word_characters'] ?? '';
        $removeSpaces = (int) $langSettings['remove_spaces'];
        $rtlScript = (bool) $langSettings['right_to_left'];

        $this->assertEquals('http://dict1.test/###', $dictLink1);
        $this->assertEquals('http://dict2.test/###', $dictLink2);
        $this->assertStringContainsString('translate.test', $translatorLink);
        $this->assertEquals(120, $textSize);
        $this->assertEquals('a-zA-Z', $regexpWordChars);
        $this->assertEquals(0, $removeSpaces);
        $this->assertFalse($rtlScript);

        // Step 4: Get TTS voice API
        $voiceApi = $service->getTtsVoiceApi($langId);
        $this->assertEquals('Google', $voiceApi);
    }

    public function testReadingDataRetrievalForNonExistentText(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextFacade();

        $headerData = $service->getTextForReading(999999);

        $this->assertNull($headerData);
    }

    public function testReadingDataRetrievalForTextWithoutAudio(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextFacade();

        // Use the second test text which has no audio
        $headerData = $service->getTextForReading(self::$testText2Id);

        $this->assertIsArray($headerData);
        $this->assertEquals('ReadControllerTestText2', $headerData['title']);
        $this->assertTrue(
            $headerData['audio_uri'] === null || $headerData['audio_uri'] === '',
            'Text without audio should have empty or null audio_uri'
        );
    }

    // ===== RTL language tests =====

    public function testReadingDataForRtlLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextFacade();

        // Create RTL language
        Connection::query(
            "INSERT INTO " . Globals::table('languages') . " (name, dict1_uri, dict2_uri, google_translate_uri, " .
            "text_size, character_substitutions, regexp_split_sentences, exceptions_split_sentences, " .
            "regexp_word_characters, remove_spaces, split_each_char, right_to_left, show_romanization) " .
            "VALUES ('ReadTestRTLLang', 'http://rtl.test/###', '', '', " .
            "100, '', '.!?', '', 'a-zA-Z', 0, 0, 1, 0)"
        );
        $rtlLangId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        // Create text with RTL language
        Connection::query(
            "INSERT INTO " . Globals::table('texts') . " (language_id, title, text, annotated_text) " .
            "VALUES (" . $rtlLangId . ", 'RTL Test', 'RTL content.', '')"
        );
        $rtlTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        // Get language settings
        $langSettings = $service->getLanguageSettingsForReading($rtlLangId);

        $this->assertIsArray($langSettings);
        $this->assertEquals(1, (int)$langSettings['right_to_left']);

        // Cleanup
        Connection::query("DELETE FROM " . Globals::table('texts') . " WHERE id = " . $rtlTextId);
        Connection::query("DELETE FROM " . Globals::table('languages') . " WHERE id = " . $rtlLangId);
    }

    // ===== Edge case tests =====

    public function testGetTextIdWithLargeNumbers(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = '2147483647'; // Max 32-bit int

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        $this->assertEquals(2147483647, $result);
    }

    public function testGetTextIdWithNegativeNumber(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = '-1';

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        // is_numeric returns true for negative numbers
        $this->assertEquals(-1, $result);
    }

    public function testGetTextIdWithDecimalNumber(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = '123.45';

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        // is_numeric returns true for decimals, will be cast to int
        $this->assertEquals(123, $result);
    }

    public function testGetTextIdWithWhitespace(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $_REQUEST['text'] = '  123  ';

        $controller = new TextReadController(
            new \Lukaisu\Modules\Text\Application\TextFacade()
        );

        $result = $controller->getTextIdFromRequest();

        // Trimmed string is numeric, cast to int
        // Note: is_numeric may behave differently with leading/trailing spaces
        // This test documents the actual behavior
        if (is_numeric($_REQUEST['text'])) {
            $this->assertEquals(123, $result);
        } else {
            $this->assertNull($result);
        }
    }

    // ===== Controller instantiation consistency =====

    public function testMultipleControllerInstantiationsAreFunctional(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $controller1 = new TextController(
            new \Lukaisu\Modules\Text\Application\TextFacade(),
            new \Lukaisu\Modules\Language\Application\LanguageFacade()
        );
        $controller2 = new TextController(
            new \Lukaisu\Modules\Text\Application\TextFacade(),
            new \Lukaisu\Modules\Language\Application\LanguageFacade()
        );

        // Both controllers should be functional
        $this->assertInstanceOf(TextController::class, $controller1);
        $this->assertInstanceOf(TextController::class, $controller2);

        // Both should have the read method
        $this->assertTrue(method_exists($controller1, 'read'));
        $this->assertTrue(method_exists($controller2, 'read'));
    }
}

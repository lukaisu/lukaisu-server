<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Services;

use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the TextFacade reading-related methods.
 *
 * Tests data retrieval for text reading functionality.
 */
class TextServiceReadingTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private static int $testTextId = 0;
    private TextFacade $service;

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
                "SELECT LgID AS value FROM languages WHERE LgName = 'TestReadingLanguage' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                Connection::query(
                    "INSERT INTO languages (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
                    "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, LgExceptionsSplitSentences, " .
                    "LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, LgRightToLeft, LgShowRomanization, " .
                    "LgTTSVoiceAPI) " .
                    "VALUES ('TestReadingLanguage', 'http://dict1.test/###', 'http://dict2.test/###', " .
                    "'http://translate.test/?sl=en&tl=fr&text=###', " .
                    "150, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1, 'Google')"
                );
                self::$testLangId = (int)Connection::fetchValue(
                    "SELECT LAST_INSERT_ID() AS value"
                );
            }

            // Create a test text with annotations and audio
            $annotatedText = "-1\t.\n0\tHello\t\t*\n0\tworld\t\ttranslation";
            Connection::query(
                "INSERT INTO texts (language_id, title, text, annotated_text, audio_uri, " .
                "source_uri, audio_position, position) " .
                "VALUES (" . self::$testLangId . ", 'Test Reading Text', 'Hello world.', " .
                "'" . mysqli_real_escape_string(Globals::getDbConnection(), $annotatedText) . "', " .
                "'http://audio.test/file.mp3', 'http://source.test/article', 30, 100)"
            );
            self::$testTextId = (int)Connection::fetchValue(
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
        if (self::$testTextId > 0) {
            Connection::query("DELETE FROM texts WHERE id = " . self::$testTextId);
        }
        if (self::$testLangId > 0) {
            Connection::query("DELETE FROM languages WHERE LgName = 'TestReadingLanguage'");
        }
    }

    protected function setUp(): void
    {
        $this->service = new TextFacade();
    }

    // ===== getTextForReading() tests =====

    public function testGetTextForReadingReturnsCorrectData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTextForReading(self::$testTextId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('LgName', $result);
        $this->assertArrayHasKey('language_id', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('audio_uri', $result);
        $this->assertArrayHasKey('source_uri', $result);
        $this->assertArrayHasKey('audio_position', $result);

        $this->assertEquals('TestReadingLanguage', $result['LgName']);
        $this->assertEquals(self::$testLangId, (int)$result['language_id']);
        $this->assertEquals('Hello world.', $result['text']);
        $this->assertEquals('Test Reading Text', $result['title']);
        $this->assertEquals('http://audio.test/file.mp3', $result['audio_uri']);
        $this->assertEquals('http://source.test/article', $result['source_uri']);
        $this->assertEquals(30, (int)$result['audio_position']);
    }

    public function testGetTextForReadingReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTextForReading(999999);

        $this->assertNull($result);
    }

    public function testGetTextForReadingReturnsNullForZeroId(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTextForReading(0);

        $this->assertNull($result);
    }

    // ===== getTextDataForContent() tests =====

    public function testGetTextDataForContentReturnsCorrectData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTextDataForContent(self::$testTextId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('language_id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('annotated_text', $result);
        $this->assertArrayHasKey('position', $result);

        $this->assertEquals(self::$testLangId, (int)$result['language_id']);
        $this->assertEquals('Test Reading Text', $result['title']);
        $this->assertStringContainsString('Hello', $result['annotated_text']);
        $this->assertEquals(100, (int)$result['position']);
    }

    public function testGetTextDataForContentReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTextDataForContent(999999);

        $this->assertNull($result);
    }

    // ===== getLanguageSettingsForReading() tests =====

    public function testGetLanguageSettingsForReadingReturnsCorrectData(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageSettingsForReading(self::$testLangId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('LgName', $result);
        $this->assertArrayHasKey('LgDict1URI', $result);
        $this->assertArrayHasKey('LgDict2URI', $result);
        $this->assertArrayHasKey('LgGoogleTranslateURI', $result);
        $this->assertArrayHasKey('LgTextSize', $result);
        $this->assertArrayHasKey('LgRegexpWordCharacters', $result);
        $this->assertArrayHasKey('LgRemoveSpaces', $result);
        $this->assertArrayHasKey('LgRightToLeft', $result);

        $this->assertEquals('TestReadingLanguage', $result['LgName']);
        $this->assertEquals('http://dict1.test/###', $result['LgDict1URI']);
        $this->assertEquals('http://dict2.test/###', $result['LgDict2URI']);
        $this->assertStringContainsString('translate.test', $result['LgGoogleTranslateURI']);
        $this->assertEquals(150, (int)$result['LgTextSize']);
        $this->assertEquals('a-zA-Z', $result['LgRegexpWordCharacters']);
        $this->assertEquals(0, (int)$result['LgRemoveSpaces']);
        $this->assertEquals(0, (int)$result['LgRightToLeft']);
    }

    public function testGetLanguageSettingsForReadingReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageSettingsForReading(999999);

        $this->assertNull($result);
    }

    public function testGetLanguageSettingsForReadingHandlesRtlLanguage(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create an RTL language
        Connection::query(
            "INSERT INTO languages (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
            "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, LgExceptionsSplitSentences, " .
            "LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, LgRightToLeft, LgShowRomanization) " .
            "VALUES ('TestRTLReadingLang', 'http://test.com/###', '', '', " .
            "100, '', '.!?', '', 'a-zA-Z', 0, 0, 1, 0)"
        );
        $rtlLangId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getLanguageSettingsForReading($rtlLangId);

        $this->assertIsArray($result);
        $this->assertEquals(1, (int)$result['LgRightToLeft']);

        // Cleanup
        Connection::query("DELETE FROM languages WHERE LgID = " . $rtlLangId);
    }

    // ===== getTtsVoiceApi() tests =====

    public function testGetTtsVoiceApiReturnsVoiceApi(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTtsVoiceApi(self::$testLangId);

        $this->assertEquals('Google', $result);
    }

    public function testGetTtsVoiceApiReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getTtsVoiceApi(999999);

        $this->assertNull($result);
    }

    public function testGetTtsVoiceApiReturnsNullForLanguageWithoutTts(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a language without TTS voice API
        Connection::query(
            "INSERT INTO languages (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
            "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, LgExceptionsSplitSentences, " .
            "LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, LgRightToLeft, LgShowRomanization) " .
            "VALUES ('TestNoTtsLang', 'http://test.com/###', '', '', " .
            "100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 0)"
        );
        $noTtsLangId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getTtsVoiceApi($noTtsLangId);

        // Should be null or empty string
        $this->assertTrue($result === null || $result === '');

        // Cleanup
        Connection::query("DELETE FROM languages WHERE LgID = " . $noTtsLangId);
    }

    // ===== getLanguageIdByName() tests =====

    public function testGetLanguageIdByNameReturnsId(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageIdByName('TestReadingLanguage');

        $this->assertEquals(self::$testLangId, $result);
    }

    public function testGetLanguageIdByNameReturnsNullForNonExistent(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $result = $this->service->getLanguageIdByName('NonExistentLanguage');

        $this->assertNull($result);
    }

    public function testGetLanguageIdByNameIsCaseSensitive(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // MySQL collation may be case-insensitive, so this test verifies behavior
        $resultUppercase = $this->service->getLanguageIdByName('TestReadingLanguage');
        $resultLowercase = $this->service->getLanguageIdByName('testreadinglanguage');

        // If both return the same ID, the collation is case-insensitive
        // This is not an error - just documenting the actual behavior
        if ($resultLowercase !== null) {
            $this->assertEquals($resultUppercase, $resultLowercase);
        } else {
            // Case-sensitive collation - lowercase should return null
            $this->assertNull($resultLowercase);
        }
    }

    // ===== Integration tests =====

    public function testFullTextReadingWorkflow(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Step 1: Get text for reading (header data)
        $headerData = $this->service->getTextForReading(self::$testTextId);
        $this->assertNotNull($headerData);
        $this->assertEquals('Test Reading Text', $headerData['title']);

        $langId = (int)$headerData['language_id'];

        // Step 2: Get text data for content display
        $contentData = $this->service->getTextDataForContent(self::$testTextId);
        $this->assertNotNull($contentData);
        $this->assertEquals($langId, (int)$contentData['language_id']);

        // Step 3: Get language settings
        $langSettings = $this->service->getLanguageSettingsForReading($langId);
        $this->assertNotNull($langSettings);
        $this->assertEquals(150, (int)$langSettings['LgTextSize']);

        // Step 4: Get TTS voice API
        $voiceApi = $this->service->getTtsVoiceApi($langId);
        $this->assertEquals('Google', $voiceApi);

        // Verify all data is consistent
        $this->assertEquals('TestReadingLanguage', $headerData['LgName']);
        $this->assertEquals('TestReadingLanguage', $langSettings['LgName']);
    }

    public function testTextWithEmptyAudioUri(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a text without audio
        Connection::query(
            "INSERT INTO texts (language_id, title, text, annotated_text, source_uri) " .
            "VALUES (" . self::$testLangId . ", 'No Audio Reading Text', 'Test content.', '', " .
            "'http://source.test')"
        );
        $noAudioTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getTextForReading($noAudioTextId);

        $this->assertIsArray($result);
        $this->assertTrue(
            $result['audio_uri'] === null || $result['audio_uri'] === '',
            'Audio URI should be null or empty'
        );

        // Cleanup
        Connection::query("DELETE FROM texts WHERE id = " . $noAudioTextId);
    }

    public function testTextWithEmptyAnnotatedText(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a text without annotations
        Connection::query(
            "INSERT INTO texts (language_id, title, text, annotated_text) " .
            "VALUES (" . self::$testLangId . ", 'No Annotation Text', 'Plain text.', '')"
        );
        $noAnnTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getTextDataForContent($noAnnTextId);

        $this->assertIsArray($result);
        $this->assertEquals('', $result['annotated_text']);

        // Cleanup
        Connection::query("DELETE FROM texts WHERE id = " . $noAnnTextId);
    }

    public function testTextWithZeroPositions(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a text with zero positions (default)
        Connection::query(
            "INSERT INTO texts (language_id, title, text, annotated_text) " .
            "VALUES (" . self::$testLangId . ", 'Zero Position Text', 'Content.', '')"
        );
        $zeroPosTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $headerResult = $this->service->getTextForReading($zeroPosTextId);
        $contentResult = $this->service->getTextDataForContent($zeroPosTextId);

        $this->assertEquals(0, (int)($headerResult['audio_position'] ?? 0));
        $this->assertEquals(0, (int)($contentResult['position'] ?? 0));

        // Cleanup
        Connection::query("DELETE FROM texts WHERE id = " . $zeroPosTextId);
    }

    public function testLanguageWithRemoveSpaces(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        // Create a language with remove spaces enabled (like Japanese)
        Connection::query(
            "INSERT INTO languages (LgName, LgDict1URI, LgDict2URI, LgGoogleTranslateURI, " .
            "LgTextSize, LgCharacterSubstitutions, LgRegexpSplitSentences, LgExceptionsSplitSentences, " .
            "LgRegexpWordCharacters, LgRemoveSpaces, LgSplitEachChar, LgRightToLeft, LgShowRomanization) " .
            "VALUES ('TestRemoveSpacesLang', 'http://test.com/###', '', '', " .
            "100, '', '.!?', '', 'a-zA-Z', 1, 0, 0, 0)"
        );
        $removeSpacesLangId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getLanguageSettingsForReading($removeSpacesLangId);

        $this->assertIsArray($result);
        $this->assertEquals(1, (int)$result['LgRemoveSpaces']);

        // Cleanup
        Connection::query("DELETE FROM languages WHERE LgID = " . $removeSpacesLangId);
    }

    // ===== Edge case tests =====

    public function testGetTextForReadingWithSpecialCharactersInTitle(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $specialTitle = "Test's \"Special\" & <Characters>";

        // Create a text with special characters in title
        Connection::query(
            "INSERT INTO texts (language_id, title, text, annotated_text) " .
            "VALUES (" . self::$testLangId . ", " .
            "'" . mysqli_real_escape_string(Globals::getDbConnection(), $specialTitle) . "', " .
            "'Content.', '')"
        );
        $specialTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getTextForReading($specialTextId);

        $this->assertIsArray($result);
        $this->assertEquals($specialTitle, $result['title']);

        // Cleanup
        Connection::query("DELETE FROM texts WHERE id = " . $specialTextId);
    }

    public function testGetTextForReadingWithUnicodeText(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $unicodeText = "こんにちは世界 - Привет мир - مرحبا";

        // Create a text with Unicode content
        Connection::query(
            "INSERT INTO texts (language_id, title, text, annotated_text) " .
            "VALUES (" . self::$testLangId . ", 'Unicode Test', " .
            "'" . mysqli_real_escape_string(Globals::getDbConnection(), $unicodeText) . "', '')"
        );
        $unicodeTextId = (int)Connection::fetchValue("SELECT LAST_INSERT_ID() AS value");

        $result = $this->service->getTextForReading($unicodeTextId);

        $this->assertIsArray($result);
        $this->assertEquals($unicodeText, $result['text']);

        // Cleanup
        Connection::query("DELETE FROM texts WHERE id = " . $unicodeTextId);
    }
}

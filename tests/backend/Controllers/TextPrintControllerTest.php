<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Controllers;

use Lukaisu\Modules\Text\Http\TextPrintController;
use Lukaisu\Shared\Infrastructure\Bootstrap\EnvLoader;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\Text\Application\Services\TextPrintService;
use Lukaisu\Shared\Infrastructure\Database\Configuration;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the TextPrintController class.
 *
 * Tests controller initialization (from Text module), service integration,
 * and verifies the MVC pattern implementation.
 */
class TextPrintControllerTest extends TestCase
{
    private static bool $dbConnected = false;
    private static int $testLangId = 0;
    private static int $testTextId = 0;
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
            // Create a test language if it doesn't exist
            $langTable = Globals::table('languages');
            $existingLang = Connection::fetchValue(
                "SELECT id AS value FROM " . $langTable .
                " WHERE name = 'PrintControllerTestLang' LIMIT 1"
            );

            if ($existingLang) {
                self::$testLangId = (int)$existingLang;
            } else {
                Connection::query(
                    "INSERT INTO " . $langTable .
                    " (name, dict1_uri, dict2_uri, google_translate_uri, " .
                    "text_size, character_substitutions, regexp_split_sentences, " .
                    "exceptions_split_sentences, regexp_word_characters, remove_spaces, " .
                    "split_each_char, right_to_left, show_romanization) " .
                    "VALUES ('PrintControllerTestLang', 'http://test.com/###', '', " .
                    "'http://translate.google.com/?sl=en&tl=fr&###', " .
                    "100, '', '.!?', '', 'a-zA-Z', 0, 0, 0, 1)"
                );
                self::$testLangId = (int)Connection::fetchValue(
                    "SELECT LAST_INSERT_ID() AS value"
                );
            }

            // Create a test text
            $textTable = Globals::table('texts');
            $existingText = Connection::fetchValue(
                "SELECT id AS value FROM " . $textTable .
                " WHERE title = 'PrintControllerTestText' LIMIT 1"
            );

            if ($existingText) {
                self::$testTextId = (int)$existingText;
            } else {
                Connection::query(
                    "INSERT INTO " . $textTable .
                    " (language_id, title, text, annotated_text, audio_uri, source_uri) " .
                    "VALUES (" . self::$testLangId .
                    ", 'PrintControllerTestText', 'This is test text.', " .
                    "'0\tThis\t\t\n1\tis\t\t\n2\ttest\t\t\n3\ttext\t\ttranslation', " .
                    "'http://audio.test/audio.mp3', 'http://source.test')"
                );
                self::$testTextId = (int)Connection::fetchValue(
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

        // Clean up test data
        $occurrencesTable = Globals::table('word_occurrences');
        $sentencesTable = Globals::table('sentences');
        $textsTable = Globals::table('texts');
        $languagesTable = Globals::table('languages');

        Connection::query(
            "DELETE FROM " . $occurrencesTable . " WHERE text_id = " . self::$testTextId
        );
        Connection::query(
            "DELETE FROM " . $sentencesTable . " WHERE text_id = " . self::$testTextId
        );
        Connection::query(
            "DELETE FROM " . $textsTable . " WHERE title = 'PrintControllerTestText'"
        );
        Connection::query(
            "DELETE FROM " . $languagesTable . " WHERE name = 'PrintControllerTestLang'"
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

        $service = new TextPrintService();
        $controller = new TextPrintController($service);

        $this->assertInstanceOf(TextPrintController::class, $controller);
    }

    public function testControllerHasPrintService(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $printService = new TextPrintService();
        $controller = new TextPrintController($printService);
        $service = $controller->getPrintService();

        $this->assertInstanceOf(TextPrintService::class, $service);
    }

    // ===== Method existence tests =====

    public function testControllerHasRequiredMethods(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $service = new TextPrintService();
        $controller = new TextPrintController($service);

        $this->assertTrue(method_exists($controller, 'printPlain'));
        $this->assertTrue(method_exists($controller, 'printAnnotated'));
        $this->assertTrue(method_exists($controller, 'getPrintService'));
    }

    // Note: formatTermOutput tests removed as formatting logic moved to frontend
    // (see src/frontend/js/texts/text_print_app.ts)

    // ===== Service integration tests =====

    public function testGetPrintServiceReturnsService(): void
    {
        if (!self::$dbConnected) {
            $this->markTestSkipped('Database connection required');
        }

        $printService = new TextPrintService();
        $controller = new TextPrintController($printService);
        $service = $controller->getPrintService();

        $this->assertInstanceOf(TextPrintService::class, $service);

        // Verify service works
        $data = $service->getTextData(self::$testTextId);
        $this->assertIsArray($data);
        $this->assertEquals('PrintControllerTestText', $data['title']);
    }
}

<?php

/**
 * Unit tests for Language module use cases.
 *
 * Tests CreateLanguage and UpdateLanguage use cases. Pure logic methods
 * are tested directly; methods relying on static database calls are
 * tested for structure and contracts only.
 *
 * PHP version 8.1
 *
 * @category Testing
 * @package  Lukaisu\Tests\Modules\Language\Application\UseCases
 * @license  Unlicense <http://unlicense.org/>
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Tests\Modules\Language\Application\UseCases;

use Lukaisu\Modules\Language\Application\UseCases\CreateLanguage;
use Lukaisu\Modules\Language\Application\UseCases\ReparseLanguageTexts;
use Lukaisu\Modules\Language\Application\UseCases\UpdateLanguage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Language module use cases.
 *
 * Tests business logic in isolation. Methods relying on static
 * QueryBuilder/InputValidator calls are tested for structure
 * and pure helper logic. No database access required.
 *
 * @since 3.0.0
 */
class LanguageUseCasesTest extends TestCase
{
    // =========================================================================
    // CreateLanguage tests
    // =========================================================================

    /**
     * Test that CreateLanguage can be instantiated.
     */
    public function testCreateLanguageCanBeInstantiated(): void
    {
        $useCase = new CreateLanguage();
        $this->assertInstanceOf(CreateLanguage::class, $useCase);
    }

    /**
     * Test getLanguageDataFromRequest returns array with all expected keys.
     *
     * This tests the method structure when no $_REQUEST data is set.
     * InputValidator::getString returns '' by default for missing keys,
     * and InputValidator::has returns false.
     */
    public function testGetLanguageDataFromRequestReturnsExpectedKeys(): void
    {
        // Clear any existing request data
        $originalRequest = $_REQUEST;
        $_REQUEST = [];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            $this->assertIsArray($data);

            $expectedKeys = [
                'name', 'dict1_uri', 'dict2_uri', 'google_translate_uri',
                'dict1_popup', 'dict2_popup', 'google_translate_popup',
                'source_lang', 'target_lang', 'export_template',
                'text_size', 'character_substitutions',
                'regexp_split_sentences', 'exceptions_split_sentences',
                'regexp_word_characters', 'parser_type', 'remove_spaces',
                'split_each_char', 'right_to_left', 'tts_voice_api',
                'show_romanization', 'local_dict_mode',
            ];

            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
            }
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    /**
     * Test getLanguageDataFromRequest returns correct types for boolean fields.
     */
    public function testGetLanguageDataFromRequestBooleanFields(): void
    {
        $originalRequest = $_REQUEST;
        $_REQUEST = [];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            // Boolean fields should be false when not in request
            $this->assertFalse($data['dict1_popup']);
            $this->assertFalse($data['dict2_popup']);
            $this->assertFalse($data['google_translate_popup']);
            $this->assertFalse($data['remove_spaces']);
            $this->assertFalse($data['split_each_char']);
            $this->assertFalse($data['right_to_left']);
            $this->assertFalse($data['show_romanization']);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    /**
     * Test getLanguageDataFromRequest with populated request data.
     */
    public function testGetLanguageDataFromRequestWithData(): void
    {
        $originalRequest = $_REQUEST;
        $_REQUEST = [
            'name' => 'French',
            'dict1_uri' => 'https://dict.example.com/###',
            'dict2_uri' => '',
            'google_translate_uri' => 'https://translate.example.com',
            'dict1_popup' => '1',
            'dict2_popup' => '',
            'google_translate_popup' => '',
            'source_lang' => 'fr',
            'target_lang' => 'en',
            'export_template' => '$w\\t$t',
            'text_size' => '150',
            'character_substitutions' => '',
            'regexp_split_sentences' => '.!?',
            'exceptions_split_sentences' => 'Mr.',
            'regexp_word_characters' => 'a-zA-Z\x{00C0}-\x{00FF}',
            'parser_type' => '',
            'remove_spaces' => '',
            'split_each_char' => '',
            'right_to_left' => '',
            'tts_voice_api' => '',
            'show_romanization' => '',
            'local_dict_mode' => '0',
        ];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            $this->assertSame('French', $data['name']);
            $this->assertSame('https://dict.example.com/###', $data['dict1_uri']);
            $this->assertTrue($data['dict1_popup']);
            $this->assertSame('fr', $data['source_lang']);
            $this->assertSame('en', $data['target_lang']);
            $this->assertSame('150', $data['text_size']);
            $this->assertSame('.!?', $data['regexp_split_sentences']);
            $this->assertSame(0, $data['local_dict_mode']);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    /**
     * Test getLanguageDataFromRequest defaults for nullable fields.
     */
    public function testGetLanguageDataFromRequestNullableDefaults(): void
    {
        $originalRequest = $_REQUEST;
        $_REQUEST = [];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            // Source/target lang default to null when empty
            $this->assertNull($data['source_lang']);
            $this->assertNull($data['target_lang']);
            $this->assertNull($data['parser_type']);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    /**
     * Test getLanguageDataFromRequest default for text_size.
     */
    public function testGetLanguageDataFromRequestTextSizeDefault(): void
    {
        $originalRequest = $_REQUEST;
        $_REQUEST = [];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            $this->assertSame('100', $data['text_size']);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    /**
     * Test getLanguageDataFromRequest local_dict_mode is int.
     */
    public function testGetLanguageDataFromRequestLocalDictModeIsInt(): void
    {
        $originalRequest = $_REQUEST;
        $_REQUEST = ['local_dict_mode' => '2'];

        try {
            $useCase = new CreateLanguage();
            $data = $useCase->getLanguageDataFromRequest();

            $this->assertIsInt($data['local_dict_mode']);
            $this->assertSame(2, $data['local_dict_mode']);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    // =========================================================================
    // UpdateLanguage tests
    // =========================================================================

    /**
     * Test that UpdateLanguage can be instantiated with default reparse use case.
     */
    public function testUpdateLanguageCanBeInstantiatedWithDefaults(): void
    {
        $useCase = new UpdateLanguage();
        $this->assertInstanceOf(UpdateLanguage::class, $useCase);
    }

    /**
     * Test that UpdateLanguage can be instantiated with injected reparse use case.
     */
    public function testUpdateLanguageCanBeInstantiatedWithInjectedReparse(): void
    {
        /** @var ReparseLanguageTexts&MockObject $reparse */
        $reparse = $this->createMock(ReparseLanguageTexts::class);

        $useCase = new UpdateLanguage($reparse);
        $this->assertInstanceOf(UpdateLanguage::class, $useCase);
    }

    /**
     * Test that UpdateLanguage accepts null for reparse use case.
     */
    public function testUpdateLanguageAcceptsNullReparse(): void
    {
        $useCase = new UpdateLanguage(null);
        $this->assertInstanceOf(UpdateLanguage::class, $useCase);
    }
}

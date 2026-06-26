<?php

/**
 * Import Text Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Application\UseCases;

use Lukaisu\Modules\Language\Domain\ValueObject\LanguageId;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\TextParsing;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Modules\Text\Domain\Text;
use Lukaisu\Modules\Text\Domain\TextRepositoryInterface;
use Lukaisu\Modules\Text\Domain\ValueObject\TextId;

/**
 * Use case for importing/creating texts.
 *
 * Handles single text creation.
 */
class ImportText
{
    private TextRepositoryInterface $textRepository;

    /**
     * Constructor.
     *
     * @param TextRepositoryInterface $textRepository Text repository
     */
    public function __construct(TextRepositoryInterface $textRepository)
    {
        $this->textRepository = $textRepository;
    }

    /**
     * Create a new text.
     *
     * @param int    $languageId Language ID
     * @param string $title      Title
     * @param string $text       Text content
     * @param string $audioUri   Audio URI (optional)
     * @param string $sourceUri  Source URI (optional)
     *
     * @return array{message: string, textId: int|null}
     */
    public function execute(
        int $languageId,
        string $title,
        string $text,
        string $audioUri = '',
        string $sourceUri = ''
    ): array {
        // Remove soft hyphens
        $text = $this->removeSoftHyphens($text);

        // Validate text length
        if (!$this->validateTextLength($text)) {
            return [
                'message' => __('text.flash.text_too_long'),
                'textId' => null
            ];
        }

        // Create and save text
        $textEntity = Text::create(
            LanguageId::fromInt($languageId),
            $title,
            $text
        );
        $textEntity->setMediaUri($audioUri);
        $textEntity->setSourceUri($sourceUri);

        $textId = $this->textRepository->save($textEntity);

        // Parse text
        TextParsing::parseAndSave($text, $languageId, $textId);

        $bindings = [$textId];
        $sentenceCount = (int)Connection::preparedFetchValue(
            "SELECT COUNT(*) AS cnt FROM sentences WHERE text_id = ?"
            . UserScopedQuery::forTablePrepared('sentences', $bindings, '', 'texts'),
            $bindings,
            'cnt'
        );
        $bindings = [$textId];
        $itemCount = (int)Connection::preparedFetchValue(
            "SELECT COUNT(*) AS cnt FROM word_occurrences WHERE text_id = ?"
            . UserScopedQuery::forTablePrepared('word_occurrences', $bindings, '', 'texts'),
            $bindings,
            'cnt'
        );

        $message = "Text saved. Sentences: {$sentenceCount}, Items: {$itemCount}";

        return ['message' => $message, 'textId' => $textId];
    }

    /**
     * Validate text length (max 65000 bytes for MySQL TEXT column).
     *
     * @param string $text Text to validate
     *
     * @return bool True if valid, false if too long
     */
    public function validateTextLength(string $text): bool
    {
        return strlen($text) <= 65000;
    }

    /**
     * Remove soft hyphens from text.
     *
     * @param string $text Text to clean
     *
     * @return string Cleaned text
     */
    private function removeSoftHyphens(string $text): string
    {
        return str_replace("\xC2\xAD", "", $text);
    }
}

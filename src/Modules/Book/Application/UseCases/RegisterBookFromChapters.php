<?php

/**
 * Register Book From Chapters Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Book\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Book\Application\UseCases;

use Lukaisu\Modules\Book\Domain\Book;
use Lukaisu\Modules\Book\Domain\BookRepositoryInterface;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\UserScopedQuery;
use Lukaisu\Shared\Infrastructure\Globals;
use RuntimeException;

/**
 * Register a server book entity over chapter texts the client already created.
 *
 * The bundled client imports an EPUB on-device (parsing it in the browser and
 * creating one text per chapter via the texts API — which works offline). When a
 * server is connected, it then calls this use case to register a book over those
 * texts, so the book shows up in the book list and the reader gains chapter
 * navigation + progress. Unlike {@see CreateBookFromTexts} (which splits one long
 * text and creates the chapter texts itself), the texts already exist here; this
 * only creates the `books` row and links the texts to it.
 *
 * The chapter text ids come from the request, so each is checked against the
 * current user's own texts before it is linked — a caller cannot fold another
 * user's texts into its book.
 */
class RegisterBookFromChapters
{
    private BookRepositoryInterface $bookRepository;

    /**
     * @param BookRepositoryInterface $bookRepository Book repository
     */
    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    /**
     * Register a book over already-created chapter texts.
     *
     * @param int                                    $languageId Language id
     * @param string                                 $title      Book title
     * @param string|null                            $author     Author (optional)
     * @param list<array{textId: int, title: string}> $chapters  Chapters, in order
     * @param int|null                               $userId     Current user id
     *
     * @return array{success: bool, message: string, bookId: int|null, chapterCount: int}
     */
    public function execute(
        int $languageId,
        string $title,
        ?string $author,
        array $chapters,
        ?int $userId = null
    ): array {
        $title = trim($title);
        if ($languageId <= 0 || $title === '' || empty($chapters)) {
            return $this->failure(__('book.flash.invalid_book_id'));
        }

        // Keep only chapters whose text the current user actually owns, in the
        // order the client sent them.
        $requestedIds = [];
        foreach ($chapters as $chapter) {
            $textId = $chapter['textId'];
            if ($textId > 0) {
                $requestedIds[] = $textId;
            }
        }
        $ownedIds = $this->filterOwnedTextIds($requestedIds);
        if (empty($ownedIds)) {
            return $this->failure(__('book.flash.no_chapters'));
        }
        $owned = array_flip($ownedIds);

        $orderedChapters = [];
        foreach ($chapters as $chapter) {
            $textId = $chapter['textId'];
            if ($textId > 0 && isset($owned[$textId])) {
                $orderedChapters[] = [
                    'textId' => $textId,
                    'title' => $chapter['title'],
                ];
            }
        }

        $book = Book::create($languageId, $title, $author, null, 'epub', null, $userId);

        $this->bookRepository->beginTransaction();
        try {
            $bookId = $this->bookRepository->save($book);

            $chapterNum = 0;
            foreach ($orderedChapters as $chapter) {
                $chapterNum++;
                $this->linkTextToBook($chapter['textId'], $bookId, $chapterNum, $chapter['title']);
            }

            $this->bookRepository->updateChapterCount($bookId, $chapterNum);
            $this->bookRepository->commit();

            return [
                'success' => true,
                'message' => __('book.flash.created_from_text', ['title' => $title, 'count' => $chapterNum]),
                'bookId' => $bookId,
                'chapterCount' => $chapterNum,
            ];
        } catch (\Throwable $e) {
            $this->bookRepository->rollback();
            throw new RuntimeException('Failed to register book: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Narrow a list of text ids to those owned by the current user.
     *
     * In single-user mode the list is returned unchanged; in multi-user mode a
     * scoped SELECT drops any foreign ids.
     *
     * @param int[] $ids Requested text ids
     *
     * @return int[] Owned ids (subset of $ids)
     */
    private function filterOwnedTextIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $bindings = [];
        $inClause = Connection::buildPreparedInClause($ids, $bindings);
        $userScope = UserScopedQuery::forTablePrepared('texts', $bindings);
        if ($userScope === '') {
            return array_values(array_map('intval', $ids));
        }
        $rows = Connection::preparedFetchAll(
            'SELECT id FROM ' . Globals::table('texts') . ' WHERE id in ' . $inClause . $userScope,
            $bindings
        );
        $owned = [];
        foreach ($rows as $row) {
            $owned[] = (int) $row['id'];
        }
        return $owned;
    }

    /**
     * Link a text record to a book (scoped to the current user's texts).
     *
     * @param int    $textId       Text id (already confirmed owned)
     * @param int    $bookId       Book id
     * @param int    $chapterNum   Chapter number
     * @param string $chapterTitle Chapter title
     */
    private function linkTextToBook(int $textId, int $bookId, int $chapterNum, string $chapterTitle): void
    {
        $bindings = [$bookId, $chapterNum, $chapterTitle, $textId];
        $userScope = UserScopedQuery::forTablePrepared('texts', $bindings);
        Connection::preparedExecute(
            'UPDATE ' . Globals::table('texts') .
            ' SET book_id = ?, chapter_num = ?, chapter_title = ? WHERE id = ?' . $userScope,
            $bindings
        );
    }

    /**
     * @param string $message Failure message
     *
     * @return array{success: bool, message: string, bookId: int|null, chapterCount: int}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message, 'bookId' => null, 'chapterCount' => 0];
    }
}

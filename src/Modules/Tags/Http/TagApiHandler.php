<?php

/**
 * Tag API Handler
 *
 * Handles REST API endpoints for tag operations.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Tags\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Tags\Http;

use Lukaisu\Api\V1\Response;
use Lukaisu\Modules\Tags\Application\TagsFacade;
use Lukaisu\Shared\Http\ApiRoutableInterface;
use Lukaisu\Shared\Http\ApiRoutableTrait;
use Lukaisu\Shared\Infrastructure\Http\JsonResponse;

/**
 * API handler for tag endpoints.
 *
 * Handles:
 * - GET /api/v1/tags - Get all tags (both term and text)
 * - GET /api/v1/tags/term - Get term tags only
 * - GET /api/v1/tags/text - Get text tags only
 */
class TagApiHandler implements ApiRoutableInterface
{
    use ApiRoutableTrait;

    /**
     * Route a GET request to the appropriate handler.
     *
     * @param list<string>         $fragments URL path fragments
     * @param array<string, mixed> $params    Query parameters
     *
     * @return JsonResponse
     */
    public function routeGet(array $fragments, array $params): JsonResponse
    {
        if ($this->frag($fragments, 1) === 'manage') {
            return $this->handleManage();
        }
        return $this->handleGet(array_slice($fragments, 1));
    }

    /**
     * GET /tags/manage — every term and text tag with its usage count, for the
     * bundled tag-management page (mirrors the local router's `listTagsForManagement`).
     *
     * @return JsonResponse
     */
    private function handleManage(): JsonResponse
    {
        return Response::success([
            'term' => $this->toManageItems(TagsFacade::forTermTags()->getList('', 'text', 1, 0)),
            'text' => $this->toManageItems(TagsFacade::forTextTags()->getList('', 'text', 1, 0)),
        ]);
    }

    /**
     * Reduce TagsFacade::getList() rows to the `{id, name, count}` shape the
     * client's `TagManageItem` expects.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array{id: int, name: string, count: int}>
     */
    private function toManageItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['text'] ?? ''),
                'count' => (int) ($row['usageCount'] ?? 0),
            ];
        }
        return $items;
    }

    /**
     * PUT /tags/term/{id} or /tags/text/{id} — rename a tag (comment preserved).
     *
     * @param list<string>         $fragments URL fragments (['tags', 'term'|'text', id]).
     * @param array<string, mixed> $params    JSON body ({ name }).
     *
     * @return JsonResponse
     */
    public function routePut(array $fragments, array $params): JsonResponse
    {
        $facade = $this->facadeForType($this->frag($fragments, 1));
        if ($facade === null) {
            return Response::error('Expected /tags/term/{id} or /tags/text/{id}', 404);
        }
        $id = (int) $this->frag($fragments, 2);
        if ($id <= 0) {
            return Response::error('Tag id required', 400);
        }
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            return Response::error('Tag name required', 400);
        }
        $current = $facade->getById($id);
        $comment = is_array($current) ? (string) ($current['comment'] ?? '') : '';
        $result = $facade->update($id, $name, $comment);
        if (empty($result['success'])) {
            return Response::error($result['error'] ?? 'Rename failed', 400);
        }
        return Response::success(['success' => true]);
    }

    /**
     * DELETE /tags/term/{id} or /tags/text/{id} — delete a tag and its mappings.
     *
     * @param list<string>         $fragments URL fragments (['tags', 'term'|'text', id]).
     * @param array<string, mixed> $params    Unused.
     *
     * @return JsonResponse
     */
    public function routeDelete(array $fragments, array $params): JsonResponse
    {
        unset($params);
        $facade = $this->facadeForType($this->frag($fragments, 1));
        if ($facade === null) {
            return Response::error('Expected /tags/term/{id} or /tags/text/{id}', 404);
        }
        $id = (int) $this->frag($fragments, 2);
        if ($id <= 0) {
            return Response::error('Tag id required', 400);
        }
        $facade->delete($id);
        return Response::success(['success' => true]);
    }

    /**
     * Resolve the term/text TagsFacade for a `/tags/{type}/...` sub-path.
     *
     * @param string $type 'term' or 'text'.
     *
     * @return TagsFacade|null Null for an unknown type (→ 404).
     */
    private function facadeForType(string $type): ?TagsFacade
    {
        return match ($type) {
            'term' => TagsFacade::forTermTags(),
            'text' => TagsFacade::forTextTags(),
            default => null,
        };
    }

    /**
     * Handle GET request for tags.
     *
     * @param array $fragments URL path fragments after /tags
     *
     * @return JsonResponse
     */
    public function handleGet(array $fragments): JsonResponse
    {
        $type = isset($fragments[0]) ? (string) $fragments[0] : '';

        switch ($type) {
            case 'term':
                return Response::success(TagsFacade::getAllTermTags());
            case 'text':
                return Response::success(TagsFacade::getAllTextTags());
            default:
                // Return both tag types
                return Response::success([
                    'term' => TagsFacade::getAllTermTags(),
                    'text' => TagsFacade::getAllTextTags(),
                ]);
        }
    }

    /**
     * Handle request routing.
     *
     * @param string $method    HTTP method
     * @param array  $fragments URL fragments
     *
     * @return JsonResponse
     */
    public function handle(string $method, array $fragments): JsonResponse
    {
        switch (strtoupper($method)) {
            case 'GET':
                return $this->handleGet($fragments);
            default:
                return Response::error('Method not allowed', 405);
        }
    }
}

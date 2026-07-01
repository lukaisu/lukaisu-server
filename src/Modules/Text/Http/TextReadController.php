<?php

/**
 * Text Read Controller - Text display interface
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Text\Application\Services\TextDisplayService;
use Lukaisu\Modules\Text\Application\Services\TextNavigationService;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;

/**
 * Controller for the improved-text display interface.
 *
 * The reader (`read`) and parse-preview (`check`) pages were retired under the
 * headless-server cut (Phase R): GET /text/{id}/read and /text/check 302 to the
 * bundle, and the parse-preview posts to /api/v1/texts/check — so their PHP
 * render paths were unreachable. `display` (the annotated improved-text view) is
 * NOT bundle-redirected and is kept until it too moves to the client.
 */
class TextReadController extends BaseController
{
    private const MODULE_VIEWS = __DIR__ . '/../Views';
    private TextDisplayService $displayService;

    public function __construct(
        ?TextDisplayService $displayService = null
    ) {
        parent::__construct();
        $this->displayService = $displayService ?? new TextDisplayService();
    }

    /**
     * Display improved text.
     *
     * @param int|null $text Text ID (injected from route parameter)
     *
     * @return RedirectResponse|null Redirect response or null if rendered
     *
     * @psalm-suppress UnusedVariable Variables are used in included view files
     */
    public function display(?int $text = null): ?RedirectResponse
    {
        $textId = $text ?? $this->paramInt('text', 0) ?? 0;

        if ($textId === 0) {
            return $this->redirect('/text/edit');
        }

        $annotatedText = $this->displayService->getAnnotatedText($textId);
        if (strlen($annotatedText) <= 0) {
            return $this->redirect('/text/edit');
        }

        $settings = $this->displayService->getTextDisplaySettings($textId);
        if ($settings === null) {
            return $this->redirect('/text/edit');
        }

        $headerData = $this->displayService->getHeaderData($textId);
        if ($headerData === null) {
            return $this->redirect('/text/edit');
        }

        $title = $headerData['title'];
        $audio = $headerData['audio'];
        $sourceUri = $headerData['sourceUri'];
        $textSize = $settings['textSize'];
        $rtlScript = $settings['rtlScript'];

        $textLinks = (new TextNavigationService())->getPreviousAndNextTextLinks(
            $textId,
            'display_impr_text.php?text=',
            true,
            ' &nbsp; &nbsp; '
        );

        $mediaPlayerHtml = (new \Lukaisu\Modules\Admin\Application\Services\MediaService())
            ->getMediaPlayerHtml($audio);

        $annotations = $this->displayService->parseAnnotations($annotatedText);

        $this->displayService->saveCurrentText($textId);

        PageLayoutHelper::renderPageStartNobody('Display');
        include self::MODULE_VIEWS . '/display_main.php';
        PageLayoutHelper::renderPageEnd();

        return null;
    }
}

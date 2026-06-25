<?php

/**
 * Text Read Controller - Text reading and display interface
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Text\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Text\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Text\Application\TextFacade;
use Lukaisu\Modules\Text\Application\Services\TextDisplayService;
use Lukaisu\Modules\Text\Application\Services\TextNavigationService;
use Lukaisu\Shared\Infrastructure\Database\Settings;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use Lukaisu\Modules\Activity\Infrastructure\MySqlActivityRepository;

/**
 * Controller for text reading and display interface.
 *
 * Handles:
 * - Text reading interface (read)
 * - Text display/print modes (display)
 * - Text checking (check)
 *
 * @since 3.0.0
 */
class TextReadController extends BaseController
{
    private const MODULE_VIEWS = __DIR__ . '/../Views';
    private TextFacade $textService;
    private TextDisplayService $displayService;

    public function __construct(
        ?TextFacade $textService = null,
        ?TextDisplayService $displayService = null
    ) {
        parent::__construct();
        $this->textService = $textService ?? new TextFacade();
        $this->displayService = $displayService ?? new TextDisplayService();
    }

    /**
     * Read text interface.
     *
     * @param int|null $text Text ID (injected from route parameter)
     *
     * @return RedirectResponse|null Redirect response or null if rendered
     *
     * @psalm-suppress UnusedVariable Variables are used in included view files
     */
    public function read(?int $text = null): ?RedirectResponse
    {
        $textId = $this->getTextIdFromRequest($text);

        if ($textId === null) {
            return $this->redirect('/text/edit');
        }

        return $this->renderReadPage($textId);
    }

    /**
     * Get text ID from request parameters.
     *
     * @param int|null $injectedId Text ID injected from route parameter
     *
     * @return int|null Text ID or null
     */
    public function getTextIdFromRequest(?int $injectedId = null): ?int
    {
        if ($injectedId !== null) {
            return $injectedId;
        }
        $textId = $this->paramInt('text');
        if ($textId !== null) {
            return $textId;
        }
        $startId = $this->paramInt('start');
        if ($startId !== null) {
            return $startId;
        }
        return null;
    }

    /**
     * Render the text reading page.
     *
     * @param int $textId Text ID
     *
     * @return RedirectResponse|null Redirect response or null if rendered
     *
     * @psalm-suppress UnusedVariable Variables are used in included view files
     */
    public function renderReadPage(int $textId): ?RedirectResponse
    {
        $headerData = $this->textService->getTextForReading($textId);
        if ($headerData === null) {
            return $this->redirect('/text/edit');
        }

        (new MySqlActivityRepository())->incrementTextsRead();

        $title = (string) $headerData['title'];
        $langId = (int) $headerData['language_id'];
        $sourceUri = (string) ($headerData['source_uri'] ?? '');

        // The reader's chrome is now fetched client-side from the API: the audio
        // player from /texts/{id}/audio (audio_player.php + audioPlayer) and the
        // chapter nav from /texts/{id}/book-context (book_nav_renderer.ts), so
        // neither is rendered server-side here.

        Settings::savePerUser('currenttext', $textId);

        PageLayoutHelper::renderPageStartNobody('Read', 'full-width');
        include self::MODULE_VIEWS . '/read_desktop.php';
        PageLayoutHelper::renderPageEnd();

        return null;
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

    /**
     * Check text.
     *
     * @param array $params Route parameters
     *
     * @return void
     *
     * @psalm-suppress UnusedVariable Variables are used in included view files
     */
    public function check(array $params): void
    {
        PageLayoutHelper::renderPageStart('Check a Text', true);

        $op = $this->param('op');
        if ($op === 'Check') {
            echo '<p><input type="button" value="&lt;&lt; Back" data-action="history-back" /></p>';
            $this->textService->checkText(
                $this->param('text'),
                $this->paramInt('language_id', 0) ?? 0
            );
            echo '<p><input type="button" value="&lt;&lt; Back" data-action="history-back" /></p>';
        } else {
            $languageData = [];
            $translateUris = $this->textService->getLanguageTranslateUris();
            foreach ($translateUris as $lgId => $uri) {
                $languageData[$lgId] = \Lukaisu\Shared\Infrastructure\Http\UrlUtilities::langFromDict($uri);
            }
            $languageFacade = new \Lukaisu\Modules\Language\Application\LanguageFacade();
            $languages = $languageFacade->getLanguagesForSelect();
            $languagesOption = \Lukaisu\Shared\UI\Helpers\SelectOptionsBuilder::forLanguages(
                $languages,
                Settings::get('currentlanguage'),
                '[Choose...]'
            );
            unset($languageData, $languagesOption);

            // Cut-over: the parse-preview form is served by the bundled client.
            // GET /text/check redirects to /app/text-check.html (see routes.php),
            // so this render path is unreachable; the PHP view (check_form.php)
            // was removed. (The POST `op=Check` branch above still echoes results.)
        }

        PageLayoutHelper::renderPageEnd();
    }
}

<?php

/**
 * Home Controller - Dashboard and home page
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Home\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Home\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Modules\Home\Application\HomeFacade;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\UI\Helpers\PageLayoutHelper;

/**
 * Controller for home/dashboard page.
 */
class HomeController extends BaseController
{
    private HomeFacade $homeFacade;
    private LanguageFacade $languageFacade;

    /**
     * Create a new HomeController.
     *
     * @param HomeFacade     $homeFacade     Home facade for dashboard data
     * @param LanguageFacade $languageFacade Language facade for language operations
     */
    public function __construct(HomeFacade $homeFacade, LanguageFacade $languageFacade)
    {
        parent::__construct();
        $this->homeFacade = $homeFacade;
        $this->languageFacade = $languageFacade;
    }

    /**
     * Home page (replaces home.php)
     *
     * @param array $params Route parameters
     *
     * @return void
     */
    public function index(array $params): void
    {
        // Cut-over: the dashboard is served by the bundled client. GET `/`
        // redirects to /app/home.html (see routes.php), so this handler is
        // unreachable and the PHP view (Home/Views/index.php) + its dashboard
        // data computation were removed. Kept as a thin shell only because the
        // route registration still names it.
        PageLayoutHelper::renderPageStart("Home", true, 'home');
        PageLayoutHelper::renderPageEnd();
    }

    /**
     * Get the HomeFacade instance.
     *
     * @return HomeFacade
     */
    public function getHomeFacade(): HomeFacade
    {
        return $this->homeFacade;
    }
}

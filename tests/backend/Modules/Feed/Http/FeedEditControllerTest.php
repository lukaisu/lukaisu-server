<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\Feed\Http;

use Lukaisu\Modules\Feed\Application\FeedFacade;
use Lukaisu\Modules\Feed\Http\FeedEditController;
use Lukaisu\Modules\Language\Application\LanguageFacade;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FeedEditController.
 *
 * The legacy visual wizard / browse / index / edit rendering was deleted; the
 * controller now only backs the bundled Svelte FeedFormPage island: the native
 * create/update form POST coexistence, the JSON config data routes, and delete.
 */
class FeedEditControllerTest extends TestCase
{
    /** @var FeedFacade&MockObject */
    private FeedFacade $feedFacade;

    /** @var LanguageFacade&MockObject */
    private LanguageFacade $languageFacade;

    /** @var FlashMessageService&MockObject */
    private FlashMessageService $flashService;

    private FeedEditController $controller;

    protected function setUp(): void
    {
        $this->feedFacade = $this->createMock(FeedFacade::class);
        $this->languageFacade = $this->createMock(LanguageFacade::class);
        $this->flashService = $this->createMock(FlashMessageService::class);

        $this->controller = new FeedEditController(
            $this->feedFacade,
            $this->languageFacade,
            $this->flashService
        );
    }

    /**
     * Create a controller with redirect() stubbed out to prevent exit().
     *
     * @return FeedEditController&MockObject
     */
    private function createControllerWithRedirectStub(): FeedEditController
    {
        $controller = $this->getMockBuilder(FeedEditController::class)
            ->setConstructorArgs([
                $this->feedFacade,
                $this->languageFacade,
                $this->flashService,
            ])
            ->onlyMethods(['redirect'])
            ->getMock();

        $controller->method('redirect')
            ->willReturnCallback(function (string $url): void {
                // no-op: prevents header() + exit() in tests
            });

        return $controller;
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    #[Test]
    public function constructorCreatesValidController(): void
    {
        $this->assertInstanceOf(FeedEditController::class, $this->controller);
    }

    #[Test]
    public function constructorSetsFeedFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(FeedEditController::class, 'feedFacade');

        $this->assertSame($this->feedFacade, $reflection->getValue($this->controller));
    }

    #[Test]
    public function constructorSetsLanguageFacadeProperty(): void
    {
        $reflection = new \ReflectionProperty(FeedEditController::class, 'languageFacade');

        $this->assertSame($this->languageFacade, $reflection->getValue($this->controller));
    }

    #[Test]
    public function constructorSetsFlashServiceProperty(): void
    {
        $reflection = new \ReflectionProperty(FeedEditController::class, 'flashService');

        $this->assertSame($this->flashService, $reflection->getValue($this->controller));
    }

    #[Test]
    public function constructorSetsViewPathProperty(): void
    {
        $reflection = new \ReflectionProperty(FeedEditController::class, 'viewPath');

        $viewPath = $reflection->getValue($this->controller);
        $this->assertStringEndsWith('/Views/', $viewPath);
    }

    #[Test]
    public function constructorWithDefaultFlash(): void
    {
        $controller = new FeedEditController(
            $this->feedFacade,
            $this->languageFacade
        );

        $flashRef = new \ReflectionProperty(FeedEditController::class, 'flashService');
        $this->assertInstanceOf(FlashMessageService::class, $flashRef->getValue($controller));
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    #[Test]
    public function classUsesFeedFlashTrait(): void
    {
        $reflection = new \ReflectionClass(FeedEditController::class);
        $traitNames = array_map(
            fn(\ReflectionClass $t) => $t->getName(),
            $reflection->getTraits()
        );

        $this->assertContains(
            'Lukaisu\Modules\Feed\Http\FeedFlashTrait',
            $traitNames
        );
    }

    #[Test]
    public function classHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(FeedEditController::class);

        $expectedMethods = ['newFeed', 'editFeed', 'deleteFeed', 'configNew', 'configEdit'];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "FeedEditController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    // =========================================================================
    // newFeed tests
    // =========================================================================

    #[Test]
    public function newFeedCreatesFeedAndRedirectsOnPost(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $_REQUEST = [
            'save_feed' => '1',
            'language_id' => '2',
            'name' => 'New Feed',
            'source_uri' => 'http://example.com/rss',
            'article_section_tags' => '',
            'filter_tags' => '',
            'options' => 'tag:news,',
        ];

        $this->feedFacade->expects($this->once())
            ->method('createFeed')
            ->with($this->callback(function (array $data) {
                return $data['name'] === 'New Feed'
                    && $data['language_id'] === '2'
                    && $data['options'] === 'tag:news';
            }))
            ->willReturn(7);

        $this->flashService->expects($this->once())->method('success');

        $controller->newFeed([]);
    }

    #[Test]
    public function newFeedRedirectsWithoutSaveFlag(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $_REQUEST = [];

        $this->feedFacade->expects($this->never())->method('createFeed');

        $controller->newFeed([]);
    }

    // =========================================================================
    // editFeed tests
    // =========================================================================

    #[Test]
    public function editFeedRedirectsWhenFeedNotFound(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $this->feedFacade->method('getFeedById')
            ->with(999)
            ->willReturn(null);

        $this->flashService->expects($this->once())
            ->method('error')
            ->with('Feed not found');

        $controller->editFeed(999);
    }

    #[Test]
    public function editFeedUpdatesFeedAndRedirectsOnPost(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $feed = [
            'id' => 1,
            'language_id' => 5,
            'name' => 'Test',
            'source_uri' => 'http://test.com',
            'article_section_tags' => '',
            'filter_tags' => '',
            'options' => '',
            'update_interval' => 0,
        ];

        $this->feedFacade->method('getFeedById')->with(1)->willReturn($feed);

        $_REQUEST = [
            'update_feed' => '1',
            'language_id' => '5',
            'name' => 'Updated Feed',
            'source_uri' => 'http://test.com/rss',
            'article_section_tags' => '',
            'filter_tags' => '',
            'options' => 'tag:news,',
        ];

        $this->feedFacade->expects($this->once())
            ->method('updateFeed')
            ->with(
                1,
                $this->callback(function (array $data) {
                    return $data['name'] === 'Updated Feed'
                        && $data['options'] === 'tag:news';
                })
            );

        $this->flashService->expects($this->once())->method('success');

        $controller->editFeed(1);
    }

    #[Test]
    public function editFeedRedirectsWithoutUpdateFlag(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $feed = [
            'id' => 1,
            'language_id' => 5,
            'name' => 'Test',
            'source_uri' => 'http://test.com',
            'article_section_tags' => '',
            'filter_tags' => '',
            'options' => '',
            'update_interval' => 0,
        ];

        $this->feedFacade->method('getFeedById')->with(1)->willReturn($feed);

        $_REQUEST = [];

        $this->feedFacade->expects($this->never())->method('updateFeed');

        $controller->editFeed(1);
    }

    // =========================================================================
    // deleteFeed tests
    // =========================================================================

    #[Test]
    public function deleteFeedCallsFacadeWithStringId(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $this->feedFacade->expects($this->once())
            ->method('deleteFeeds')
            ->with('42')
            ->willReturn(['feeds' => 1]);

        $this->flashService->expects($this->once())
            ->method('success')
            ->with('Feed deleted successfully');

        $controller->deleteFeed(42);
    }

    #[Test]
    public function deleteFeedShowsErrorWhenNoFeedsDeleted(): void
    {
        $controller = $this->createControllerWithRedirectStub();

        $this->feedFacade->expects($this->once())
            ->method('deleteFeeds')
            ->with('99')
            ->willReturn(['feeds' => 0]);

        $this->flashService->expects($this->once())
            ->method('error')
            ->with('Failed to delete feed');

        $controller->deleteFeed(99);
    }

    // =========================================================================
    // Method signature tests
    // =========================================================================

    #[Test]
    public function newFeedMethodAcceptsArrayParam(): void
    {
        $method = new \ReflectionMethod(FeedEditController::class, 'newFeed');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('params', $params[0]->getName());
    }

    #[Test]
    public function editFeedMethodAcceptsIntParam(): void
    {
        $method = new \ReflectionMethod(FeedEditController::class, 'editFeed');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    #[Test]
    public function deleteFeedMethodAcceptsIntParam(): void
    {
        $method = new \ReflectionMethod(FeedEditController::class, 'deleteFeed');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }
}

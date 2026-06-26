<?php

/**
 * Book Module Service Provider
 *
 * Registers all services for the Book module.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Book
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Book;

use Lukaisu\Shared\Infrastructure\Container\Container;
use Lukaisu\Shared\Infrastructure\Container\ServiceProviderInterface;
// Domain
use Lukaisu\Modules\Book\Domain\BookRepositoryInterface;
// Infrastructure
use Lukaisu\Modules\Book\Infrastructure\MySqlBookRepository;
// Services
use Lukaisu\Modules\Book\Application\Services\EpubParserService;
use Lukaisu\Modules\Book\Application\Services\TextSplitterService;
// Use Cases
use Lukaisu\Modules\Book\Application\UseCases\ImportEpub;
use Lukaisu\Modules\Book\Application\UseCases\CreateBookFromTexts;
use Lukaisu\Modules\Book\Application\UseCases\GetBookList;
use Lukaisu\Modules\Book\Application\UseCases\GetBookById;
use Lukaisu\Modules\Book\Application\UseCases\DeleteBook;
// Application
use Lukaisu\Modules\Book\Application\BookFacade;
// Text module dependencies
use Lukaisu\Modules\Text\Domain\TextRepositoryInterface;
// Http
use Lukaisu\Modules\Book\Http\BookController;
use Lukaisu\Modules\Book\Http\BookApiHandler;

/**
 * Service provider for the Book module.
 *
 * Registers the BookRepositoryInterface, all use cases,
 * BookFacade, BookController, and BookApiHandler.
 */
class BookServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container): void
    {
        // Register Services
        $container->singleton(EpubParserService::class, function (Container $_c) {
            return new EpubParserService();
        });

        $container->singleton(TextSplitterService::class, function (Container $_c) {
            return new TextSplitterService();
        });

        // Register Repository Interface binding
        $container->singleton(BookRepositoryInterface::class, function (Container $_c) {
            return new MySqlBookRepository();
        });

        // Register MySqlBookRepository as concrete implementation
        $container->singleton(MySqlBookRepository::class, function (Container $c): MySqlBookRepository {
            /** @var MySqlBookRepository */
            return $c->getTyped(BookRepositoryInterface::class);
        });

        // Register Use Cases
        $this->registerUseCases($container);

        // Register Facade
        $container->singleton(BookFacade::class, function (Container $c) {
            return new BookFacade(
                $c->getTyped(BookRepositoryInterface::class)
            );
        });

        // Register Controller
        $container->singleton(BookController::class, function (Container $c) {
            return new BookController(
                $c->getTyped(BookFacade::class)
            );
        });

        // Register API Handler
        $container->singleton(BookApiHandler::class, function (Container $c) {
            return new BookApiHandler(
                $c->getTyped(BookFacade::class)
            );
        });
    }

    /**
     * Register all use case classes.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    private function registerUseCases(Container $container): void
    {
        // ImportEpub use case
        $container->singleton(ImportEpub::class, function (Container $c) {
            return new ImportEpub(
                $c->getTyped(BookRepositoryInterface::class),
                $c->getTyped(TextRepositoryInterface::class),
                $c->getTyped(EpubParserService::class),
                $c->getTyped(TextSplitterService::class)
            );
        });

        // CreateBookFromTexts use case
        $container->singleton(CreateBookFromTexts::class, function (Container $c) {
            return new CreateBookFromTexts(
                $c->getTyped(BookRepositoryInterface::class),
                $c->getTyped(TextRepositoryInterface::class),
                $c->getTyped(TextSplitterService::class)
            );
        });

        // GetBookList use case
        $container->singleton(GetBookList::class, function (Container $c) {
            return new GetBookList(
                $c->getTyped(BookRepositoryInterface::class)
            );
        });

        // GetBookById use case
        $container->singleton(GetBookById::class, function (Container $c) {
            return new GetBookById(
                $c->getTyped(BookRepositoryInterface::class)
            );
        });

        // DeleteBook use case
        $container->singleton(DeleteBook::class, function (Container $c) {
            return new DeleteBook(
                $c->getTyped(BookRepositoryInterface::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        // No bootstrap logic needed for the Book module
    }
}

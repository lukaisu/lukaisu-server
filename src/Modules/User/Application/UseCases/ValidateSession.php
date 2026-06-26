<?php

/**
 * Validate Session Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\User\Application\UseCases
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Application\UseCases;

use Lukaisu\Modules\User\Domain\User;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\User\Domain\UserRepositoryInterface;

/**
 * Use case for validating user sessions.
 *
 * Checks if the current session is valid and restores user context.
 */
class ValidateSession
{
    /**
     * User repository.
     *
     * @var UserRepositoryInterface
     */
    private UserRepositoryInterface $repository;

    /**
     * Session key for storing the user ID.
     */
    private const SESSION_USER_ID = 'LUKAISU_USER_ID';

    /**
     * Create a new ValidateSession use case.
     *
     * @param UserRepositoryInterface $repository User repository
     */
    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the session validation.
     *
     * @return User|null The authenticated user or null if invalid
     */
    public function execute(): ?User
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION[self::SESSION_USER_ID])) {
            return null;
        }

        $userId = (int) $_SESSION[self::SESSION_USER_ID];
        $user = $this->findUser($userId);

        if ($user === null || !$user->canLogin()) {
            $this->destroySession();
            return null;
        }

        // Restore user context
        Globals::setCurrentUserId($user->id()->toInt());

        return $user;
    }

    /**
     * Find a user by ID.
     *
     * @param int $userId User ID
     *
     * @return User|null
     */
    private function findUser(int $userId): ?User
    {
        try {
            return $this->repository->find($userId);
        } catch (\RuntimeException $e) {
            // Database not initialized or query failed
            return null;
        }
    }

    /**
     * Destroy the current session.
     *
     * @return void
     */
    private function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') !== false) {
            $params = session_get_cookie_params();
            $sessionName = session_name();
            setcookie(
                $sessionName !== false ? $sessionName : 'PHPSESSID',
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? false
            );
        }

        session_destroy();
    }
}

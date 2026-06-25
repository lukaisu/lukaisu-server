<?php

/**
 * MySQL User Repository
 *
 * Infrastructure adapter for user persistence using MySQL.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\User\Infrastructure
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Infrastructure;

use DateTimeImmutable;
use Lukaisu\Modules\User\Domain\User;
use Lukaisu\Shared\Domain\ValueObjects\UserId;
use Lukaisu\Shared\Infrastructure\Database\Connection;
use Lukaisu\Shared\Infrastructure\Database\QueryBuilder;
use Lukaisu\Modules\User\Domain\UserRepositoryInterface;

/**
 * MySQL implementation of UserRepositoryInterface.
 *
 * Provides database access for user management operations.
 * Handles authentication lookups and user CRUD.
 *
 * @since 3.0.0
 */
class MySqlUserRepository implements UserRepositoryInterface
{
    /**
     * @var string Table name without prefix
     */
    private string $tableName = 'users';

    /**
     * @var string Primary key column
     */
    private string $primaryKey = 'id';

    /**
     * Get a query builder for this repository's table.
     *
     * @return QueryBuilder
     */
    private function query(): QueryBuilder
    {
        return QueryBuilder::table($this->tableName);
    }

    /**
     * Map a database row to a User entity.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return User
     */
    private function mapToEntity(array $row): User
    {
        $user = User::reconstitute(
            (int) $row['id'],
            (string) $row['username'],
            ($row['email'] ?? null) !== null ? (string) $row['email'] : null,
            $row['password_hash'] !== null ? (string) $row['password_hash'] : null,
            $row['api_token'] !== null ? (string) $row['api_token'] : null,
            $this->parseNullableDateTime($this->getNullableString($row['api_token_expires'] ?? null)),
            ($row['remember_token'] ?? null) !== null ? (string) $row['remember_token'] : null,
            $this->parseNullableDateTime($this->getNullableString($row['remember_token_expires'] ?? null)),
            ($row['password_reset_token'] ?? null) !== null ? (string) $row['password_reset_token'] : null,
            $this->parseNullableDateTime($this->getNullableString($row['password_reset_token_expires'] ?? null)),
            $this->parseNullableDateTime($this->getNullableString($row['email_verified_at'] ?? null)),
            ($row['email_verification_token'] ?? null) !== null ? (string) $row['email_verification_token'] : null,
            $this->parseNullableDateTime($this->getNullableString($row['email_verification_token_expires'] ?? null)),
            $row['wordpress_id'] !== null ? (int) $row['wordpress_id'] : null,
            ($row['google_id'] ?? null) !== null ? (string) $row['google_id'] : null,
            ($row['microsoft_id'] ?? null) !== null ? (string) $row['microsoft_id'] : null,
            $this->parseDateTime($this->getNullableString($row['created_at'] ?? null)),
            $this->parseNullableDateTime($this->getNullableString($row['last_login_at'] ?? null)),
            (bool) ($row['is_active'] ?? true),
            (string) ($row['role'] ?? User::ROLE_USER)
        );

        // Recovery code is hydrated separately so it stays out of the (already
        // long) reconstitute() signature.
        $user->setRecoveryCodeHash(
            ($row['recovery_code_hash'] ?? null) !== null ? (string) $row['recovery_code_hash'] : null
        );

        return $user;
    }

    /**
     * Map a User entity to database row.
     *
     * @param User $entity The user entity
     *
     * @return array<string, scalar|null>
     */
    private function mapToRow(User $entity): array
    {
        return [
            'id' => $entity->id()->toInt(),
            'username' => $entity->username(),
            'email' => $entity->email(),
            'password_hash' => $entity->passwordHash(),
            'api_token' => $entity->apiToken(),
            'api_token_expires' => $entity->apiTokenExpires()?->format('Y-m-d H:i:s'),
            'remember_token' => $entity->rememberToken(),
            'remember_token_expires' => $entity->rememberTokenExpires()?->format('Y-m-d H:i:s'),
            'password_reset_token' => $entity->passwordResetToken(),
            'password_reset_token_expires' => $entity->passwordResetTokenExpires()?->format('Y-m-d H:i:s'),
            'email_verified_at' => $entity->emailVerifiedAt()?->format('Y-m-d H:i:s'),
            'email_verification_token' => $entity->emailVerificationToken(),
            'email_verification_token_expires' => $entity->emailVerificationTokenExpires()?->format('Y-m-d H:i:s'),
            'wordpress_id' => $entity->wordPressId(),
            'google_id' => $entity->googleId(),
            'microsoft_id' => $entity->microsoftId(),
            'created_at' => $entity->created()->format('Y-m-d H:i:s'),
            'last_login_at' => $entity->lastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $entity->isActive() ? 1 : 0,
            'role' => $entity->role(),
            'recovery_code_hash' => $entity->recoveryCodeHash(),
        ];
    }

    /**
     * Parse a datetime string into DateTimeImmutable.
     *
     * @param string|null $datetime The datetime string
     *
     * @return DateTimeImmutable
     */
    private function parseDateTime(?string $datetime): DateTimeImmutable
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return new DateTimeImmutable();
        }
        return new DateTimeImmutable($datetime);
    }

    /**
     * Get a nullable string from a mixed value.
     *
     * @param mixed $value The value to convert
     *
     * @return string|null
     */
    private function getNullableString(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }

    /**
     * Parse a nullable datetime string.
     *
     * @param string|null $datetime The datetime string
     *
     * @return DateTimeImmutable|null
     */
    private function parseNullableDateTime(?string $datetime): ?DateTimeImmutable
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return null;
        }
        return new DateTimeImmutable($datetime);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?User
    {
        $row = $this->query()
            ->where($this->primaryKey, '=', $id)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    /**
     * {@inheritdoc}
     */
    public function save(User $entity): int
    {
        $data = $this->mapToRow($entity);
        $id = $entity->id()->toInt();

        if ($id > 0 && !$entity->id()->isNew()) {
            // Update existing
            $this->query()
                ->where($this->primaryKey, '=', $id)
                ->updatePrepared($data);
            return $id;
        }

        // Insert new
        $insertData = $data;
        unset($insertData[$this->primaryKey]);

        $newId = (int) $this->query()->insertPrepared($insertData);
        $entity->setId(UserId::fromInt($newId));

        return $newId;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $affected = $this->query()
            ->where($this->primaryKey, '=', $id)
            ->deletePrepared();

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(int $id): bool
    {
        return $this->query()
            ->where($this->primaryKey, '=', $id)
            ->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function findByUsername(string $username): ?User
    {
        $row = $this->query()
            ->where('username', '=', $username)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        $row = $this->query()
            ->where('email', '=', strtolower($email))
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByApiToken(string $token): ?User
    {
        $row = $this->query()
            ->where('api_token', '=', $token)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByRememberToken(string $token): ?User
    {
        $row = $this->query()
            ->where('remember_token', '=', $token)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByWordPressId(int $wordPressId): ?User
    {
        $row = $this->query()
            ->where('wordpress_id', '=', $wordPressId)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByGoogleId(string $googleId): ?User
    {
        $row = $this->query()
            ->where('google_id', '=', $googleId)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByMicrosoftId(string $microsoftId): ?User
    {
        $row = $this->query()
            ->where('microsoft_id', '=', $microsoftId)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->query()
            ->where('username', '=', $username);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->query()
            ->where('email', '=', strtolower($email));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->existsPrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function findActive(): array
    {
        $rows = $this->query()
            ->where('is_active', '=', 1)
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find all inactive users.
     *
     * @return User[]
     */
    public function findInactive(): array
    {
        $rows = $this->query()
            ->where('is_active', '=', 0)
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find all admin users.
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        $rows = $this->query()
            ->where('role', '=', User::ROLE_ADMIN)
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find users linked to WordPress.
     *
     * @return User[]
     */
    public function findWordPressUsers(): array
    {
        $rows = $this->query()
            ->whereNotNull('wordpress_id')
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find users linked to Google.
     *
     * @return User[]
     */
    public function findGoogleUsers(): array
    {
        $rows = $this->query()
            ->whereNotNull('google_id')
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find users linked to Microsoft.
     *
     * @return User[]
     */
    public function findMicrosoftUsers(): array
    {
        $rows = $this->query()
            ->whereNotNull('microsoft_id')
            ->orderBy('username')
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * {@inheritdoc}
     */
    public function updateLastLogin(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['last_login_at' => date('Y-m-d H:i:s')]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['password_hash' => $passwordHash]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateApiToken(int $userId, ?string $token, ?DateTimeImmutable $expires): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared([
                'api_token' => $token,
                'api_token_expires' => $expires?->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(int $userId, ?string $token, ?DateTimeImmutable $expires): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared([
                'remember_token' => $token,
                'remember_token_expires' => $expires?->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function findByPasswordResetToken(string $token): ?User
    {
        $row = $this->query()
            ->where('password_reset_token', '=', $token)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePasswordResetToken(int $userId, ?string $token, ?DateTimeImmutable $expires): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared([
                'password_reset_token' => $token,
                'password_reset_token_expires' => $expires?->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmailVerificationToken(string $token): ?User
    {
        $row = $this->query()
            ->where('email_verification_token', '=', $token)
            ->firstPrepared();

        return $row !== null ? $this->mapToEntity($row) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['is_active' => 1]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['is_active' => 0]);

        return $affected > 0;
    }

    /**
     * Update user role.
     *
     * @param int    $userId User ID
     * @param string $role   New role (user or admin)
     *
     * @return bool True if updated
     */
    public function updateRole(int $userId, string $role): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['role' => $role]);

        return $affected > 0;
    }

    /**
     * Link user to WordPress account.
     *
     * @param int $userId      User ID
     * @param int $wordPressId WordPress user ID
     *
     * @return bool True if updated
     */
    public function linkWordPress(int $userId, int $wordPressId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['wordpress_id' => $wordPressId]);

        return $affected > 0;
    }

    /**
     * Unlink user from WordPress account.
     *
     * @param int $userId User ID
     *
     * @return bool True if updated
     */
    public function unlinkWordPress(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['wordpress_id' => null]);

        return $affected > 0;
    }

    /**
     * Link user to Google account.
     *
     * @param int    $userId   User ID
     * @param string $googleId Google user ID
     *
     * @return bool True if updated
     */
    public function linkGoogle(int $userId, string $googleId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['google_id' => $googleId]);

        return $affected > 0;
    }

    /**
     * Unlink user from Google account.
     *
     * @param int $userId User ID
     *
     * @return bool True if updated
     */
    public function unlinkGoogle(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['google_id' => null]);

        return $affected > 0;
    }

    /**
     * Link user to Microsoft account.
     *
     * @param int    $userId      User ID
     * @param string $microsoftId Microsoft user ID
     *
     * @return bool True if updated
     */
    public function linkMicrosoft(int $userId, string $microsoftId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['microsoft_id' => $microsoftId]);

        return $affected > 0;
    }

    /**
     * Unlink user from Microsoft account.
     *
     * @param int $userId User ID
     *
     * @return bool True if updated
     */
    public function unlinkMicrosoft(int $userId): bool
    {
        $affected = $this->query()
            ->where('id', '=', $userId)
            ->updatePrepared(['microsoft_id' => null]);

        return $affected > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getForSelect(int $maxNameLength = 40): array
    {
        $rows = $this->query()
            ->select(['id', 'username', 'email'])
            ->where('is_active', '=', 1)
            ->orderBy('username')
            ->getPrepared();

        $result = [];

        foreach ($rows as $row) {
            $username = (string) $row['username'];
            if (mb_strlen($username, 'UTF-8') > $maxNameLength) {
                $username = mb_substr($username, 0, $maxNameLength, 'UTF-8') . '...';
            }
            $result[] = [
                'id' => (int) $row['id'],
                'username' => $username,
                'email' => (string) $row['email'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getBasicInfo(int $userId): ?array
    {
        $row = $this->query()
            ->select([
                'id',
                'username',
                'email',
                'is_active',
                'role',
            ])
            ->where('id', '=', $userId)
            ->firstPrepared();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'email' => (string) $row['email'],
            'is_active' => (bool) $row['is_active'],
            'is_admin' => $row['role'] === User::ROLE_ADMIN,
        ];
    }

    /**
     * Get users with pagination.
     *
     * @param int    $page      Page number (1-based)
     * @param int    $perPage   Items per page
     * @param string $orderBy   Column to order by
     * @param string $direction Sort direction
     *
     * @return array{items: User[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function findPaginated(
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'username',
        string $direction = 'ASC'
    ): array {
        $query = $this->query();

        $total = (clone $query)->countPrepared();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        $page = max(1, min($page, max(1, $totalPages)));
        $offset = ($page - 1) * $perPage;

        $rows = $query
            ->orderBy($orderBy, $direction)
            ->limit($perPage)
            ->offset($offset)
            ->getPrepared();

        $items = array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Search users by username or email.
     *
     * @param string $query Search query
     * @param int    $limit Maximum results
     *
     * @return User[]
     */
    public function search(string $query, int $limit = 50): array
    {
        $searchPattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';

        $sql = "SELECT * FROM users WHERE (username LIKE ? OR email LIKE ?) ORDER BY username LIMIT ?";
        $rows = Connection::preparedFetchAll($sql, [$searchPattern, $searchPattern, $limit]);

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find users who logged in recently.
     *
     * @param int $days  Number of days to look back
     * @param int $limit Maximum results
     *
     * @return User[]
     */
    public function findRecentlyActive(int $days = 30, int $limit = 50): array
    {
        $timestamp = strtotime("-{$days} days");
        $sinceDate = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());

        $rows = $this->query()
            ->where('last_login_at', '>=', $sinceDate)
            ->orderBy('last_login_at', 'DESC')
            ->limit($limit)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Find users created recently.
     *
     * @param int $days  Number of days to look back
     * @param int $limit Maximum results
     *
     * @return User[]
     */
    public function findRecentlyCreated(int $days = 30, int $limit = 50): array
    {
        $timestamp = strtotime("-{$days} days");
        $sinceDate = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());

        $rows = $this->query()
            ->where('created_at', '>=', $sinceDate)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Get user statistics.
     *
     * @return array{total: int, active: int, inactive: int, admins: int,
     *               wordpress_linked: int, google_linked: int, microsoft_linked: int}
     */
    public function getStatistics(): array
    {
        $baseQuery = $this->query();

        $total = (clone $baseQuery)->countPrepared();

        $active = (clone $baseQuery)
            ->where('is_active', '=', 1)
            ->countPrepared();

        $inactive = (clone $baseQuery)
            ->where('is_active', '=', 0)
            ->countPrepared();

        $admins = (clone $baseQuery)
            ->where('role', '=', User::ROLE_ADMIN)
            ->countPrepared();

        $wordPressLinked = (clone $baseQuery)
            ->whereNotNull('wordpress_id')
            ->countPrepared();

        $googleLinked = (clone $baseQuery)
            ->whereNotNull('google_id')
            ->countPrepared();

        $microsoftLinked = (clone $baseQuery)
            ->whereNotNull('microsoft_id')
            ->countPrepared();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'admins' => $admins,
            'wordpress_linked' => $wordPressLinked,
            'google_linked' => $googleLinked,
            'microsoft_linked' => $microsoftLinked,
        ];
    }

    /**
     * Find users with expired API tokens.
     *
     * @return User[]
     */
    public function findWithExpiredApiTokens(): array
    {
        $now = date('Y-m-d H:i:s');

        $rows = $this->query()
            ->whereNotNull('api_token')
            ->where('api_token_expires', '<', $now)
            ->getPrepared();

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $rows
        );
    }

    /**
     * Clear expired API tokens.
     *
     * @return int Number of cleared tokens
     */
    public function clearExpiredApiTokens(): int
    {
        $now = date('Y-m-d H:i:s');

        return $this->query()
            ->whereNotNull('api_token')
            ->where('api_token_expires', '<', $now)
            ->updatePrepared([
                'api_token' => null,
                'api_token_expires' => null,
            ]);
    }

    /**
     * Delete multiple users by IDs.
     *
     * @param int[] $userIds Array of user IDs
     *
     * @return int Number of deleted users
     */
    public function deleteMultiple(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', array_map('intval', $userIds))
            ->deletePrepared();
    }

    /**
     * {@inheritdoc}
     */
    public function countAdmins(): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM users "
             . "WHERE role = ? AND password_hash IS NOT NULL";
        $rows = Connection::preparedFetchAll($sql, [User::ROLE_ADMIN]);

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Count users by role.
     *
     * @return array<string, int> Role => count
     */
    public function countByRole(): array
    {
        $sql = "SELECT role, COUNT(*) as cnt FROM users GROUP BY role";
        $rows = Connection::preparedFetchAll($sql, []);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['role']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Count all users.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->query()->countPrepared();
    }
}

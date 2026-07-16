<?php

declare(strict_types=1);

namespace Tests\Integration;

use Core\Session;
use Models\User;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Services\AuthService;

#[RequiresPhpExtension('pdo_sqlite')]
final class AuthServiceTest extends TestCase
{
    public function testItRegistersAndAuthenticatesAUser(): void
    {
        [$auth, $session, $db] = $this->auth();

        $user = $auth->register('Victor', ' Victor@Example.com ', 'password-123');

        self::assertIsArray($user);
        self::assertSame('victor@example.com', $user['email']);
        self::assertArrayNotHasKey('password', $user);
        self::assertSame($user['id'], $session->get('user_id'));
        self::assertSame(1, $session->regenerations);

        $hash = $db->query('SELECT password FROM users LIMIT 1')->fetchColumn();
        self::assertIsString($hash);
        self::assertNotSame('password-123', $hash);
        self::assertTrue(password_verify('password-123', $hash));
    }

    public function testItRejectsDuplicateEmailAddresses(): void
    {
        [$auth] = $this->auth();

        self::assertNotNull($auth->register('Victor', 'victor@example.com', 'password-123'));
        self::assertNull($auth->register('Another Victor', 'VICTOR@example.com', 'password-456'));
    }

    public function testItAttemptsLoginAndResolvesTheCurrentUser(): void
    {
        [$registration, , $db] = $this->auth();
        $registered = $registration->register('Victor', 'victor@example.com', 'password-123');
        self::assertIsArray($registered);

        $session = new InMemoryAuthSession();
        $auth = new AuthService(new User($db), $session);

        self::assertFalse($auth->attempt('victor@example.com', 'wrong-password'));
        self::assertFalse($session->has('user_id'));
        self::assertTrue($auth->attempt('VICTOR@example.com', 'password-123'));
        self::assertSame('Victor', $auth->user()['name'] ?? null);
        self::assertArrayNotHasKey('password', $auth->user() ?? []);
    }

    public function testItInvalidatesTheSessionOnLogout(): void
    {
        [$auth, $session] = $this->auth();
        $auth->register('Victor', 'victor@example.com', 'password-123');

        $auth->logout();

        self::assertTrue($session->invalidated);
        self::assertFalse($auth->check());
        self::assertFalse($session->has('user_id'));
    }

    /**
     * Build an authentication service backed by an in-memory database.
     *
     * @return array{AuthService, InMemoryAuthSession, PDO}
     */
    private function auth(): array
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $session = new InMemoryAuthSession();

        return [new AuthService(new User($db), $session), $session, $db];
    }
}

final class InMemoryAuthSession extends Session
{
    public int $regenerations = 0;

    public bool $invalidated = false;

    private array $values = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->values[$key]);
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerations++;
    }

    public function invalidate(): void
    {
        $this->values = [];
        $this->invalidated = true;
        $this->regenerate();
    }
}

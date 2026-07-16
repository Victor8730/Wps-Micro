<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Request;
use Core\Response;
use Core\Session;
use Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    public function testItAllowsAuthenticatedRequests(): void
    {
        $middleware = new AuthMiddleware(new AuthSession(true));

        $response = $middleware->handle(
            new Request('GET', '/account'),
            static fn (): Response => new Response('account')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('account', $response->getContent());
    }

    public function testItReturnsJsonForUnauthenticatedApiRequests(): void
    {
        $middleware = new AuthMiddleware(new AuthSession(false));

        $response = $middleware->handle(
            new Request('GET', '/account', [], [], [], ['Accept' => 'application/json']),
            static fn (): Response => new Response('not reached')
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Unauthenticated.'],
            json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testItRedirectsUnauthenticatedBrowserRequests(): void
    {
        $middleware = new AuthMiddleware(new AuthSession(false));

        $response = $middleware->handle(
            new Request('GET', '/account'),
            static fn (): Response => new Response('not reached')
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaders()['Location'] ?? null);
    }
}

final class AuthSession extends Session
{
    public function __construct(private readonly bool $authenticated)
    {
    }

    public function has(string $key): bool
    {
        return $key === 'user_id' && $this->authenticated;
    }
}

<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Csrf;
use WpsMicro\Core\Exceptions\CsrfTokenMismatchException;
use WpsMicro\Core\Middleware\CsrfMiddleware;
use WpsMicro\Core\Request;
use WpsMicro\Core\Response;
use WpsMicro\Core\Session;

final class CsrfMiddlewareTest extends TestCase
{
    public function testItAcceptsAValidHeaderToken(): void
    {
        $csrf = new Csrf(new MemorySession());
        $middleware = new CsrfMiddleware($csrf);
        $request = new Request(
            'POST',
            '/cart',
            [],
            [],
            [],
            ['X-CSRF-Token' => $csrf->token()]
        );

        $response = $middleware->handle(
            $request,
            static fn (): Response => new Response('accepted')
        );

        self::assertSame('accepted', $response->getContent());
    }

    public function testItRejectsAnInvalidToken(): void
    {
        $middleware = new CsrfMiddleware(new Csrf(new MemorySession()));

        $this->expectException(CsrfTokenMismatchException::class);

        $middleware->handle(
            new Request('DELETE', '/cart/42', [], ['_token' => 'invalid']),
            static fn (): Response => new Response('not reached')
        );
    }
}

final class MemorySession extends Session
{
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }
}

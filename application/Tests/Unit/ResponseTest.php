<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testHeadersAreCaseInsensitiveAndCanContainMultipleValues(): void
    {
        $response = new Response('', 200, [
            'Content-Type' => 'text/plain',
            'Set-Cookie' => ['theme=dark', 'locale=en'],
        ]);

        $response->setHeader('content-type', 'application/json');
        $response->addHeader('set-cookie', 'cart=active');

        self::assertTrue($response->hasHeader('CONTENT-TYPE'));
        self::assertSame('application/json', $response->getHeader('Content-Type'));
        self::assertSame(
            ['theme=dark', 'locale=en', 'cart=active'],
            $response->getHeader('Set-Cookie')
        );
        self::assertCount(2, $response->getHeaders());
    }

    public function testItRejectsHeaderInjection(): void
    {
        $response = new Response();

        $this->expectException(\InvalidArgumentException::class);

        $response->setHeader('Location', "/login\r\nX-Injected: yes");
    }

    public function testItRejectsInvalidStatusCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Response('', 700);
    }
}

<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Exceptions\BadRequestException;
use WpsMicro\Core\Request;

final class RequestTest extends TestCase
{
    public function testItParsesJsonInputAndPreservesRawContent(): void
    {
        $content = '{"email":"victor@example.com","active":true}';
        $request = new Request(
            'POST',
            '/users?source=test',
            ['source' => 'test'],
            [],
            [],
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'application/json',
            ],
            [],
            [],
            $content
        );

        self::assertSame('/users', $request->getPath());
        self::assertSame('victor@example.com', $request->input('email'));
        self::assertTrue($request->json('active'));
        self::assertSame(['source' => 'test', 'email' => 'victor@example.com'], $request->only(['source', 'email']));
        self::assertSame($content, $request->getContent());
        self::assertTrue($request->expectsJson());
    }

    public function testItParsesUrlEncodedBodiesAndMethodOverrides(): void
    {
        $request = new Request(
            'POST',
            '/products/42',
            [],
            [],
            [],
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            [],
            [],
            '_method=PATCH&name=Updated'
        );

        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('Updated', $request->body('name'));
        self::assertTrue($request->filled('name'));
    }

    public function testItExposesFilesAndCookies(): void
    {
        $file = ['name' => 'photo.jpg', 'error' => UPLOAD_ERR_OK];
        $request = new Request('GET', '/', [], [], [], [], ['photo' => $file], ['theme' => 'dark']);

        self::assertSame($file, $request->file('photo'));
        self::assertSame('dark', $request->cookie('theme'));
    }

    public function testItRejectsInvalidJson(): void
    {
        try {
            new Request(
                'POST',
                '/',
                [],
                [],
                [],
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                [],
                [],
                '{invalid'
            );
        } catch (BadRequestException $exception) {
            self::assertSame('The JSON request body is invalid.', $exception->getMessage());
            self::assertTrue($exception->expectsJson());

            return;
        }

        self::fail('Invalid JSON was accepted.');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\ErrorHandler;
use Core\Request;
use Exceptions\BadRequestException;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/wps-micro-' . bin2hex(random_bytes(8)) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testItHidesAndLogsProductionErrors(): void
    {
        $handler = new ErrorHandler($this->config(false));
        $request = new Request('GET', '/', [], [], [], ['Accept' => 'application/json']);

        $response = $handler->render(new \RuntimeException('Sensitive failure'), $request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['message' => 'Server error'], $payload);
        self::assertStringContainsString('Sensitive failure', (string) file_get_contents($this->logPath));
    }

    public function testItRendersEscapedDebugDetails(): void
    {
        $handler = new ErrorHandler($this->config(true));
        $response = $handler->render(new \RuntimeException('<script>alert(1)</script>'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function testItRendersJsonForBadRequestBeforeRequestIsAvailable(): void
    {
        $handler = new ErrorHandler($this->config(false));
        $response = $handler->render(new BadRequestException('Invalid payload.', true));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Invalid payload.'],
            json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame('application/json; charset=utf-8', $response->getHeaders()['Content-Type']);
    }

    private function config(bool $debug): Config
    {
        return new Config([
            'app' => ['debug' => $debug],
            'logging' => ['path' => $this->logPath],
        ]);
    }
}

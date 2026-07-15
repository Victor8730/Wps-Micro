<?php

declare(strict_types=1);

namespace Tests\Integration;

use Core\Config;
use Core\Kernel;
use Core\Middleware\CsrfMiddleware;
use Core\Request;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/wps-micro-kernel-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cachePath);
    }

    public function testItHandlesARequestThroughRoutingMiddlewareControllerAndTwig(): void
    {
        $fixtures = dirname(__DIR__) . '/Fixtures';
        $kernel = new Kernel(new Config([
            'app' => [
                'debug' => false,
                'url' => 'https://example.test',
            ],
            'router' => [
                'routes_path' => $fixtures . '/routes.php',
            ],
            'middleware' => [
                'global' => [],
                'route' => [CsrfMiddleware::class],
            ],
            'session' => [],
            'logging' => [
                'path' => $this->cachePath . '/app.log',
            ],
            'twig' => [
                'views_path' => $fixtures . '/views',
                'cache_path' => $this->cachePath . '/twig',
                'auto_reload' => false,
                'autoescape' => 'html',
            ],
        ]));

        $response = $kernel->handle(new Request('GET', '/hello/Victor'));
        $session = $kernel->getContainer()->get(Session::class);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'Hello Victor via GET at https://example.test/status',
            trim($response->getContent())
        );
        self::assertFalse($session->isStarted());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}

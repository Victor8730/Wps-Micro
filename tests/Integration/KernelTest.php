<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Config;
use WpsMicro\Core\Container;
use WpsMicro\Core\Kernel;
use WpsMicro\Core\Middleware\CsrfMiddleware;
use WpsMicro\Core\Request;
use WpsMicro\Core\Router;
use WpsMicro\Core\Session;

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

    public function testJsonControllersDoNotInitializeTwig(): void
    {
        $fixtures = dirname(__DIR__) . '/Fixtures';
        $twigCachePath = $this->cachePath . '/twig-unused';
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
                'route' => [],
            ],
            'session' => [],
            'logging' => [
                'path' => $this->cachePath . '/app.log',
            ],
            'twig' => [
                'views_path' => $fixtures . '/missing-views',
                'cache_path' => $twigCachePath,
                'auto_reload' => false,
                'autoescape' => 'html',
            ],
        ]));

        $response = $kernel->handle(new Request(
            'GET',
            '/json',
            [],
            [],
            [],
            ['Accept' => 'application/json']
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
        self::assertDirectoryDoesNotExist($twigCachePath);
    }

    public function testItPreservesApplicationContainerBindings(): void
    {
        $container = new Container();
        $router = new Router();
        $container->instance(Router::class, $router);

        $kernel = new Kernel(new Config([]), $container);

        self::assertSame($router, $kernel->getContainer()->get(Router::class));
    }

    public function testItRejectsAMissingConfiguredRoutesFile(): void
    {
        $path = $this->cachePath . '/missing-routes.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Routes file is not readable: ' . $path);

        new Kernel(new Config([
            'router' => ['routes_path' => $path],
        ]));
    }

    public function testItRejectsRoutesFilesThatDoNotReturnACallable(): void
    {
        mkdir($this->cachePath, 0775, true);
        $path = $this->cachePath . '/routes.php';
        file_put_contents($path, '<?php return [];');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Routes file must return a callable: ' . $path);

        new Kernel(new Config([
            'router' => ['routes_path' => $path],
        ]));
    }

    public function testItRejectsAnUnreadableConfigurationPath(): void
    {
        $path = $this->cachePath . '/missing-config.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application configuration file is not readable: ' . $path);

        Kernel::fromConfigFile($path);
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

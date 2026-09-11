<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Console\Commands\RouteListCommand;
use WpsMicro\Core\Middleware;
use WpsMicro\Core\Request;
use WpsMicro\Core\Response;
use WpsMicro\Core\Router;

final class RouteListCommandTest extends TestCase
{
    public function testItListsRegisteredRoutes(): void
    {
        $router = new Router();
        $router->get('/products/{id}', [RouteListController::class, 'show'])
            ->whereNumber('id')
            ->middleware(RouteListMiddleware::class)
            ->name('products.show');
        $command = new RouteListCommand($router);

        ob_start();

        try {
            self::assertSame(0, $command->handle([]));
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString('Method', $output);
        self::assertStringContainsString('GET', $output);
        self::assertStringContainsString('/products/{id}', $output);
        self::assertStringContainsString('products.show', $output);
        self::assertStringContainsString(RouteListController::class.'@show', $output);
        self::assertStringContainsString(RouteListMiddleware::class, $output);
    }

    public function testItReportsAnEmptyRouteCollection(): void
    {
        $command = new RouteListCommand(new Router());

        ob_start();

        try {
            self::assertSame(0, $command->handle([]));
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame("No routes registered.\n", $output);
    }
}

final class RouteListController
{
    public function show(string $id): Response
    {
        return new Response($id);
    }
}

final class RouteListMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}

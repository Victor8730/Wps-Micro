<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Container;
use WpsMicro\Core\Middleware;
use WpsMicro\Core\MiddlewarePipeline;
use WpsMicro\Core\Request;
use WpsMicro\Core\Response;

final class MiddlewarePipelineTest extends TestCase
{
    public function testItUpdatesTheRequestBindingPassedToTheDestination(): void
    {
        $container = new Container();
        $pipeline = new MiddlewarePipeline($container);

        $response = $pipeline->handle(
            new Request('GET', '/original'),
            [ReplaceRequestMiddleware::class],
            static function (Request $request) use ($container): Response {
                $boundRequest = $container->get(Request::class);

                return new Response($request->getPath() . ':' . $boundRequest->getPath());
            }
        );

        self::assertSame('/changed:/changed', $response->getContent());
    }
}

final class ReplaceRequestMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next(new Request('GET', '/changed'));
    }
}

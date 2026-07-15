<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Container;
use Core\Middleware;
use Core\MiddlewarePipeline;
use Core\Request;
use Core\Response;
use PHPUnit\Framework\TestCase;

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

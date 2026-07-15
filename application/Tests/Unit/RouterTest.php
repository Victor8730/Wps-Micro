<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Request;
use Core\Response;
use Core\Router;
use Exceptions\HttpNotFoundException;
use Exceptions\MethodNotAllowedException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testItMatchesExplicitRoutesAndExtractsParameters(): void
    {
        $router = $this->router();
        $router->get('/products/{id}', [RouterTestController::class, 'show']);

        $match = $router->match(new Request('GET', '/products/42'));

        self::assertSame(RouterTestController::class, $match->getControllerClass());
        self::assertSame('show', $match->getActionMethod());
        self::assertSame(['id' => '42'], $match->getParameters());
    }

    public function testItRejectsMethodsThatAreNotRegisteredForThePath(): void
    {
        $router = $this->router();
        $router->get('/products/{id}', [RouterTestController::class, 'show']);
        $router->post('/products/{id}', [RouterTestController::class, 'show']);

        try {
            $router->match(new Request('DELETE', '/products/42'));
            self::fail('Expected a method not allowed exception.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET', 'POST'], $exception->getAllowedMethods());
        }
    }

    public function testItDoesNotDiscoverControllersByUrlConvention(): void
    {
        $router = $this->router();

        $this->expectException(HttpNotFoundException::class);

        $router->match(new Request('GET', '/legacy/show'));
    }

    private function router(): Router
    {
        return new Router();
    }
}

final class RouterTestController
{
    public function show(string $id): Response
    {
        return new Response($id);
    }
}

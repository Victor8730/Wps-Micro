<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Exceptions\HttpNotFoundException;
use WpsMicro\Core\Exceptions\MethodNotAllowedException;
use WpsMicro\Core\Middleware;
use WpsMicro\Core\Request;
use WpsMicro\Core\Response;
use WpsMicro\Core\Router;

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
            self::assertSame(['GET', 'HEAD', 'POST'], $exception->getAllowedMethods());
        }
    }

    public function testHeadRequestsUseAnExplicitHeadRouteBeforeGetFallback(): void
    {
        $router = $this->router();
        $router->get('/status', [RouterTestController::class, 'show']);
        $router->head('/status', [RouterTestController::class, 'head']);

        $match = $router->match(new Request('HEAD', '/status'));

        self::assertSame('head', $match->getActionMethod());
    }

    public function testHeadRequestsFallBackToGetRoutes(): void
    {
        $router = $this->router();
        $router->get('/status', [RouterTestController::class, 'show']);

        $match = $router->match(new Request('HEAD', '/status'));

        self::assertSame('show', $match->getActionMethod());
    }

    public function testItDoesNotDiscoverControllersByUrlConvention(): void
    {
        $router = $this->router();

        $this->expectException(HttpNotFoundException::class);

        $router->match(new Request('GET', '/legacy/show'));
    }

    public function testStaticRoutesTakePriorityOverDynamicRoutes(): void
    {
        $router = $this->router();
        $router->get('/products/{id}', [RouterTestController::class, 'show']);
        $router->get('/products/create', [RouterTestController::class, 'create']);

        $match = $router->match(new Request('GET', '/products/create'));

        self::assertSame('create', $match->getActionMethod());
        self::assertSame([], $match->getParameters());
    }

    public function testItMatchesConstraintsAndGeneratesNamedRouteUrls(): void
    {
        $router = $this->router();
        $router
            ->get('/products/{id}', [RouterTestController::class, 'show'])
            ->name('products.show')
            ->whereNumber('id');

        $match = $router->match(new Request('GET', '/products/42'));

        self::assertSame('products.show', $match->getName());
        self::assertSame(['id' => '42'], $match->getParameters());
        self::assertSame(
            '/products/42?tab=details',
            $router->url('products.show', ['id' => 42], ['tab' => 'details']),
        );

        $this->expectException(HttpNotFoundException::class);

        $router->match(new Request('GET', '/products/not-a-number'));
    }

    public function testNestedGroupsCombinePrefixesNamesAndMiddleware(): void
    {
        $router = $this->router();
        $router->group([
            'prefix' => '/admin',
            'name' => 'admin.',
            'middleware' => RouterGroupMiddleware::class,
        ], static function (Router $router): void {
            $router->group(['prefix' => '/products', 'name' => 'products.'], static function (Router $router): void {
                $router->get('/{id}', [RouterTestController::class, 'show'])
                    ->where('id', '[1-9][0-9]*')
                    ->name('show');
            });
        });

        $match = $router->match(new Request('GET', '/admin/products/7'));

        self::assertSame('admin.products.show', $match->getName());
        self::assertSame([RouterGroupMiddleware::class], $match->getMiddleware());
        self::assertSame('/admin/products/7', $router->url('admin.products.show', ['id' => 7]));
    }

    public function testConstraintsMayContainTheDefaultRegexDelimiter(): void
    {
        $router = $this->router();
        $router->get('/posts/{slug}', [RouterTestController::class, 'show'])
            ->where('slug', '[a-z~]+')
            ->name('posts.show');

        $match = $router->match(new Request('GET', '/posts/wps~micro'));

        self::assertSame(['slug' => 'wps~micro'], $match->getParameters());
        self::assertSame('/posts/wps~micro', $router->url('posts.show', ['slug' => 'wps~micro']));
    }

    public function testUuidConstraintsRejectInvalidIdentifiers(): void
    {
        $router = $this->router();
        $router->get('/orders/{order}', [RouterTestController::class, 'show'])
            ->whereUuid('order');

        $match = $router->match(new Request('GET', '/orders/550e8400-e29b-41d4-a716-446655440000'));

        self::assertSame(
            ['order' => '550e8400-e29b-41d4-a716-446655440000'],
            $match->getParameters(),
        );

        $this->expectException(HttpNotFoundException::class);

        $router->match(new Request('GET', '/orders/not-a-uuid'));
    }

    public function testItRejectsDuplicateRoutesAndNames(): void
    {
        $router = $this->router();
        $router->get('/products', [RouterTestController::class, 'show'])->name('products.index');

        try {
            $router->get('/products', [RouterTestController::class, 'create']);
            self::fail('A duplicate method and path were accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Duplicate route: GET /products', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route name: products.index');

        $router->post('/products', [RouterTestController::class, 'create'])->name('products.index');
    }

    public function testRenamingARouteRemovesItsPreviousName(): void
    {
        $router = $this->router();
        $route = $router->get('/products', [RouterTestController::class, 'show'])
            ->name('products.old');

        $route->name('products.index');

        self::assertSame('/products', $router->url('products.index'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Named route does not exist: products.old');

        $router->url('products.old');
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

    public function head(): Response
    {
        return new Response();
    }

    public function create(): Response
    {
        return new Response('create');
    }
}

final class RouterGroupMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}

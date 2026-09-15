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
    public function testEncodedSlashesPreserveBoundariesBetweenBroadParameters(): void
    {
        $router = $this->router();
        $router->get('/files/{folder}/{file}', [RouterTestController::class, 'show'])
            ->where(['folder' => '.+', 'file' => '.+'])->name('files');

        foreach ([
            ['folder' => 'a', 'file' => 'b/c'],
            ['folder' => 'a/b', 'file' => 'c'],
            ['folder' => 'a/b', 'file' => 'c/d'],
            ['folder' => "caf\u{00e9}/a", 'file' => '%2F/b'],
        ] as $parameters) {
            $url = $router->url('files', $parameters);
            self::assertSame($parameters, $router->match(new Request('GET', $url))->getParameters());
            self::assertSame($parameters, $router->match(new Request('HEAD', $url))->getParameters());
        }

        try {
            $router->match(new Request('POST', '/files/a/b%2Fc'));
            self::fail('Expected a method error.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET', 'HEAD'], $exception->getAllowedMethods());
        }
    }

    public function testOnlyTheFinalStandaloneParameterConsumesExtraSegments(): void
    {
        $router = $this->router();
        $router->get('/files/{folder}/{file}', [RouterTestController::class, 'show'])
            ->where(['folder' => '.+', 'file' => '.+']);

        self::assertSame(
            ['folder' => 'a', 'file' => 'b/c'],
            $router->match(new Request('GET', '/files/a/b/c'))->getParameters(),
        );

        $router->get('/archive/{folder}/edit', [RouterTestController::class, 'show'])->where('folder', '.+');
        self::assertSame(
            ['folder' => 'a/b'],
            $router->match(new Request('GET', '/archive/a%2Fb/edit'))->getParameters(),
        );
        $this->expectException(HttpNotFoundException::class);
        $router->match(new Request('GET', '/archive/a/b/edit'));
    }

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

    public function testMiddlewareInstancesKeepTheirIdentityAndOrder(): void
    {
        $router = $this->router();
        $outer = new RouterGroupMiddleware();
        $inner = new RouterGroupMiddleware();
        $routeMiddleware = new class () implements Middleware {
            public string $role = 'admin';

            public function handle(Request $request, callable $next): Response
            {
                return new Response($this->role, 403);
            }
        };

        $router->group(['middleware' => $outer], static function (Router $router) use ($inner, $routeMiddleware): void {
            $router->group(['middleware' => [$inner]], static function (Router $router) use ($routeMiddleware): void {
                $router->get('/private', [RouterTestController::class, 'show'])
                    ->middleware(new RouterGroupMiddleware())
                    ->middleware($routeMiddleware)
                    ->middleware([RouterGroupMiddleware::class]);
            });
        });

        $middleware = $router->match(new Request('GET', '/private'))->getMiddleware();

        self::assertCount(5, $middleware);
        self::assertSame($outer, $middleware[0]);
        self::assertSame($inner, $middleware[1]);
        self::assertInstanceOf(RouterGroupMiddleware::class, $middleware[2]);
        self::assertSame($routeMiddleware, $middleware[3]);
        self::assertSame(RouterGroupMiddleware::class, $middleware[4]);
    }

    public function testEncodedParametersRoundTripThroughConstraints(): void
    {
        $router = $this->router();
        $router->get('/city/{name}', [RouterTestController::class, 'show'])
            ->where('name', '[\\p{L}0-9 +%]+')->name('city');

        foreach (["caf\u{00e9}", "\u{041a}\u{0438}\u{0457}\u{0432}", 'New York', 'A+B', '%2F'] as $name) {
            $url = $router->url('city', ['name' => $name]);
            self::assertSame('/city/'.rawurlencode($name), $url);
            self::assertSame(['name' => $name], $router->match(new Request('GET', $url))->getParameters());
        }
    }

    public function testEncodedNumbersAndMultipleParametersMatchTheirConstraints(): void
    {
        $router = $this->router();
        $router->get('/files/{name}.{extension}/{id}', [RouterTestController::class, 'show'])
            ->where(['name' => '[\\p{L}]+', 'extension' => 'txt|csv'])->whereNumber('id')->name('file');

        $parameters = ['name' => "caf\u{00e9}", 'extension' => 'txt', 'id' => '42'];
        $url = $router->url('file', $parameters);

        self::assertSame($parameters, $router->match(new Request('GET', $url))->getParameters());
        self::assertSame($parameters, $router->match(new Request('GET', '/files/caf%C3%A9.txt/%34%32'))->getParameters());
    }

    public function testEncodedSlashesAreAllowedOnlyInsideMatchingParameters(): void
    {
        $router = $this->router();
        $router->get('/files/{path}', [RouterTestController::class, 'show'])
            ->where('path', '.+')->name('files');

        foreach (['a/b', "caf\u{00e9}/b", '%2F', '/a/'] as $path) {
            $url = $router->url('files', ['path' => $path]);
            self::assertSame(['path' => $path], $router->match(new Request('GET', $url))->getParameters());
        }

        self::assertSame(['path' => 'a/b'], $router->match(new Request('GET', '/files/a/b'))->getParameters());
        self::assertSame(['path' => 'a/b'], $router->match(new Request('GET', '/files/a%2fb'))->getParameters());
    }

    public function testEncodedSlashesCannotReplaceLiteralSeparatorsOrBypassDefaultConstraints(): void
    {
        $router = $this->router();
        $router->get('/items/{id}', [RouterTestController::class, 'show']);
        $router->get('/items/{id}/edit', [RouterTestController::class, 'show']);
        $router->get('/items/{id}/edit/{action}', [RouterTestController::class, 'show']);

        foreach (['/items/a%2Fb', '/items/a%2Fedit', '/items/a%2fedit/b', '/items%2Fa', '/items/a%2F'] as $path) {
            foreach (['GET', 'POST'] as $method) {
                try {
                    $router->match(new Request($method, $path));
                    self::fail('An encoded separator was accepted: '.$path);
                } catch (HttpNotFoundException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }

    public function testEncodedConstraintsAreAlsoUsedForHeadAndMethodErrors(): void
    {
        $router = $this->router();
        $router->get('/city/{name}', [RouterTestController::class, 'show'])->where('name', '[\\p{L}]+');
        $router->post('/city/{name}', [RouterTestController::class, 'show'])->whereNumber('name');

        self::assertSame(['name' => "caf\u{00e9}"], $router->match(new Request('HEAD', '/city/caf%C3%A9'))->getParameters());

        try {
            $router->match(new Request('DELETE', '/city/caf%C3%A9'));
            self::fail('Expected a method error.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET', 'HEAD'], $exception->getAllowedMethods());
        }

        $this->expectException(HttpNotFoundException::class);
        $router->match(new Request('DELETE', '/city/%21'));
    }

    public function testNameIndexesStayConsistentAfterRenamesAndRejectedDuplicates(): void
    {
        $router = $this->router();
        $first = $router->add(['GET', 'POST'], '/first', [RouterTestController::class, 'show'])->name('first');
        $second = $router->get('/second', [RouterTestController::class, 'show'])->name('second');
        $first->name('first');

        try {
            $first->name('second');
            self::fail('A duplicate name was accepted.');
        } catch (\InvalidArgumentException) {
            self::assertSame('/first', $router->url('first'));
            self::assertSame('/second', $router->url('second'));
            self::assertSame('first', $router->match(new Request('POST', '/first'))->getName());
        }

        $first->name('renamed');
        $second->name('first');

        self::assertSame('/first', $router->url('renamed'));
        self::assertSame('/second', $router->url('first'));
        self::assertSame('renamed', $router->match(new Request('GET', '/first'))->getName());
        self::assertSame('renamed', $router->match(new Request('POST', '/first'))->getName());
    }

    public function testDynamicDuplicateChecksAreAtomicAcrossMethods(): void
    {
        $router = $this->router();
        $router->get('/items/{id}', [RouterTestController::class, 'show'])->whereNumber('id');

        try {
            $router->add(['POST', 'GET'], '/items/{id}', [RouterTestController::class, 'show']);
            self::fail('A duplicate dynamic route was accepted.');
        } catch (\InvalidArgumentException) {
            self::assertCount(1, $router->getRoutes());
        }

        $router->post('/items/{id}', [RouterTestController::class, 'show']);
        self::assertCount(2, $router->getRoutes());
    }

    public function testItRegistersALargeNamedRouteTable(): void
    {
        $router = $this->router();

        for ($index = 0; $index < 4000; ++$index) {
            $router->get('/items/'.$index.'/{id}', [RouterTestController::class, 'show'])->name('items.'.$index);
        }

        self::assertCount(4000, $router->getRoutes());
        self::assertSame('/items/0/1', $router->url('items.0', ['id' => 1]));
        self::assertSame('/items/3999/1', $router->url('items.3999', ['id' => 1]));
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

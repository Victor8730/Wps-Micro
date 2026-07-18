<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Container;
use Core\Dispatcher;
use Core\ErrorHandler;
use Core\Middleware;
use Core\MiddlewarePipeline;
use Core\Request;
use Core\Response;
use Core\Router;
use Core\Session;
use Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class DispatcherTest extends TestCase
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

    public function testGlobalMiddlewareWrapsControllerErrorResponses(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $router->get('/failure', [FailingController::class, 'show']);
        $container = new Container();
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config),
            [ResponseHeaderMiddleware::class]
        );

        $response = $dispatcher->dispatch(new Request('GET', '/failure'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('yes', $response->getHeaders()['X-Global'] ?? null);
    }

    public function testItReturnsJsonForMethodErrorsWhenRequested(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $router->get('/products/{id}', [FailingController::class, 'show']);
        $container = new Container();
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config)
        );

        $response = $dispatcher->dispatch(new Request(
            'DELETE',
            '/products/42',
            [],
            [],
            [],
            ['Accept' => 'application/json']
        ));
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, HEAD', $response->getHeaders()['Allow'] ?? null);
        self::assertSame(['message' => 'Method not allowed'], $payload);
    }

    public function testJsonValidationDoesNotStartOrWriteToTheSession(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $router->post('/register', [ValidationFailureController::class, 'store']);
        $container = new Container();
        $session = new CapturingSession();
        $container->instance(Session::class, $session);
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config)
        );

        $response = $dispatcher->dispatch(new Request(
            'POST',
            '/register',
            [],
            [
                'email' => 'victor@example.com',
                'password' => 'secret',
                'password_confirmation' => 'secret',
                '_token' => 'csrf-token',
            ],
            [],
            ['Accept' => 'application/json']
        ));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $session->flashed);
    }

    public function testBrowserValidationUsesOnlySameOriginRedirectTargets(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $router->post('/register', [ValidationFailureController::class, 'store']);
        $container = new Container();
        $session = new CapturingSession();
        $container->instance(Session::class, $session);
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config),
            appUrl: 'https://example.test'
        );

        $sameOrigin = $dispatcher->dispatch(new Request(
            'POST',
            '/register',
            [],
            ['email' => 'victor@example.com', 'password' => 'secret'],
            [],
            ['Referer' => 'https://example.test/register?step=2']
        ));
        $externalOrigin = $dispatcher->dispatch(new Request(
            'POST',
            '/register',
            [],
            ['email' => 'victor@example.com'],
            [],
            ['Referer' => 'https://attacker.test/phishing']
        ));

        self::assertSame('/register?step=2', $sameOrigin->getHeaders()['Location'] ?? null);
        self::assertSame('/register', $externalOrigin->getHeaders()['Location'] ?? null);
        self::assertSame(['email' => 'victor@example.com'], $session->flashed['old_input'] ?? null);
    }

    public function testItUsesTheConfiguredNotFoundAction(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $container = new Container();
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config),
            [],
            [],
            [NotFoundController::class, 'show']
        );

        $response = $dispatcher->dispatch(new Request('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Custom not found page', $response->getContent());
    }

    public function testHeadResponsesKeepHeadersButDoNotReturnContent(): void
    {
        $config = new Config([
            'app' => ['debug' => false],
            'logging' => ['path' => $this->logPath],
        ]);
        $router = new Router();
        $router->get('/status', [HeadResponseController::class, 'show']);
        $container = new Container();
        $dispatcher = new Dispatcher(
            $router,
            $container,
            new MiddlewarePipeline($container),
            new ErrorHandler($config)
        );

        $response = $dispatcher->dispatch(new Request('HEAD', '/status'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame('ready', $response->getHeader('X-Status'));
        self::assertSame('11', $response->getHeader('Content-Length'));
    }
}

final class FailingController
{
    public function show(): Response
    {
        throw new \RuntimeException('Controller failed.');
    }
}

final class ResponseHeaderMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request)->setHeader('X-Global', 'yes');
    }
}

final class ValidationFailureController
{
    public function store(): Response
    {
        throw new ValidationException([
            'email' => ['email is invalid.'],
        ]);
    }
}

final class NotFoundController
{
    public function show(): Response
    {
        return new Response('Custom not found page');
    }
}

final class HeadResponseController
{
    public function show(): Response
    {
        return new Response('service-ok!', 200, ['X-Status' => 'ready']);
    }
}

final class CapturingSession extends Session
{
    public array $flashed = [];

    public function flash(string $key, mixed $value): void
    {
        $this->flashed[$key] = $value;
    }
}

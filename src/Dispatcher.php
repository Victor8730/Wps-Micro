<?php

declare(strict_types=1);

namespace WpsMicro\Core;

use WpsMicro\Core\Exceptions\CsrfTokenMismatchException;
use WpsMicro\Core\Exceptions\HttpNotFoundException;
use WpsMicro\Core\Exceptions\MethodNotAllowedException;
use WpsMicro\Core\Exceptions\ValidationException;

class Dispatcher
{
    /**
     * Router used to match requests.
     */
    private Router $router;

    /**
     * Container used to create controller instances.
     */
    private Container $container;

    /**
     * Middleware pipeline.
     */
    private MiddlewarePipeline $pipeline;

    /**
     * Error handler for uncaught application exceptions.
     */
    private ErrorHandler $errorHandler;

    /**
     * Middleware executed before route matching.
     */
    private array $globalMiddleware;

    /**
     * Middleware executed after a route is matched.
     */
    private array $routeMiddleware;

    /**
     * Application action used to render browser 404 responses.
     */
    private array $notFoundAction;

    /**
     * Configured application URL used to validate redirect origins.
     */
    private string $appUrl;

    /**
     * Create a dispatcher.
     */
    public function __construct(
        Router $router,
        Container $container,
        MiddlewarePipeline $pipeline,
        ErrorHandler $errorHandler,
        array $globalMiddleware = [],
        array $routeMiddleware = [],
        array $notFoundAction = [],
        string $appUrl = ''
    ) {
        $this->router = $router;
        $this->container = $container;
        $this->pipeline = $pipeline;
        $this->errorHandler = $errorHandler;
        $this->globalMiddleware = $globalMiddleware;
        $this->routeMiddleware = $routeMiddleware;
        $this->notFoundAction = $notFoundAction;
        $this->appUrl = rtrim($appUrl, '/');
    }

    /**
     * Dispatch the request and return a response.
     */
    public function dispatch(Request $request): Response
    {
        $response = $this->handleExceptions($request, function () use ($request): Response {
            return $this->pipeline->handle(
                $request,
                $this->globalMiddleware,
                function (Request $request): Response {
                    return $this->handleExceptions($request, function () use ($request): Response {
                        return $this->dispatchRoute($request);
                    });
                }
            );
        });

        return $this->prepareResponse($request, $response);
    }

    /**
     * Match and execute a route through its middleware.
     */
    private function dispatchRoute(Request $request): Response
    {
        $match = $this->router->match($request);

        return $this->pipeline->handle(
            $request,
            array_merge($this->routeMiddleware, $match->getMiddleware()),
            function (Request $request) use ($match): Response {
                $controller = $this->container->make($match->getControllerClass());

                return $this->executeAction(
                    $controller,
                    $match->getActionMethod(),
                    $match->getParameters()
                );
            }
        );
    }

    /**
     * Convert known HTTP exceptions into responses.
     */
    private function handleExceptions(Request $request, callable $handler): Response
    {
        try {
            return $handler();
        } catch (MethodNotAllowedException $e) {
            return $this->methodNotAllowed($request, $e);
        } catch (HttpNotFoundException $e) {
            return $this->notFound($request);
        } catch (CsrfTokenMismatchException $e) {
            return $this->clientError($request, 'CSRF token mismatch.', 419);
        } catch (ValidationException $e) {
            return $this->validationFailed($request, $e);
        } catch (\Throwable $exception) {
            return $this->errorHandler->render($exception, $request);
        }
    }

    /**
     * Build a method not allowed response.
     */
    private function methodNotAllowed(Request $request, MethodNotAllowedException $exception): Response
    {
        return $this->clientError($request, 'Method not allowed', 405, [
            'Allow' => implode(', ', $exception->getAllowedMethods()),
        ]);
    }

    /**
     * Build a text or JSON client error response.
     */
    private function clientError(
        Request $request,
        string $message,
        int $statusCode,
        array $headers = []
    ): Response {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], $statusCode, $headers);
        }

        $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/plain; charset=UTF-8';

        return new Response($message, $statusCode, $headers);
    }

    /**
     * Execute a controller action and normalize the result to a response.
     */
    private function executeAction(object $controller, string $actionMethod, array $parameters = []): Response
    {
        $result = $this->callAction($controller, $actionMethod, $parameters);

        if (!$result instanceof Response) {
            throw new \UnexpectedValueException(sprintf(
                'Controller action %s::%s() must return %s.',
                $controller::class,
                $actionMethod,
                Response::class
            ));
        }

        return $result;
    }

    /**
     * Call the action with named route parameters.
     */
    private function callAction(object $controller, string $actionMethod, array $parameters): mixed
    {
        $reflection = new \ReflectionMethod($controller, $actionMethod);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new HttpNotFoundException();
        }

        return $reflection->invokeArgs($controller, $arguments);
    }

    /**
     * Build the default 404 response.
     */
    private function notFound(Request $request): Response
    {
        if ($request->expectsJson()) {
            return $this->clientError($request, 'Page not found', 404);
        }

        if (count($this->notFoundAction) !== 2) {
            return new Response('Page not found', 404);
        }

        [$controllerClass, $actionMethod] = $this->notFoundAction;

        if (!is_string($controllerClass) || !is_string($actionMethod)) {
            return new Response('Page not found', 404);
        }

        $this->container->instance(Request::class, $request);
        $controller = $this->container->make($controllerClass);

        return $this->executeAction($controller, $actionMethod)
            ->setStatusCode(404);
    }

    /**
     * Build a validation failure response.
     */
    private function validationFailed(Request $request, ValidationException $exception): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['errors' => $exception->errors()], 422);
        }

        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('errors', $exception->errors());
        $session->flash('old_input', $this->flashableInput($request));

        return new RedirectResponse($this->validationRedirectTarget($request));
    }

    /**
     * Remove tokens and password fields before flashing request input.
     */
    private function flashableInput(Request $request): array
    {
        $input = $request->getRequest();

        foreach (array_keys($input) as $key) {
            $normalized = strtolower(str_replace(['-', '.'], '_', (string) $key));

            if (in_array($normalized, ['_token', '_method'], true) || str_contains($normalized, 'password')) {
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * Apply request-method-specific response semantics.
     */
    private function prepareResponse(Request $request, Response $response): Response
    {
        if ($request->getMethod() !== 'HEAD') {
            return $response;
        }

        if (!$response->hasHeader('Content-Length')) {
            $response->setHeader('Content-Length', (string) strlen($response->getContent()));
        }

        return $response->setContent('');
    }

    /**
     * Return a same-origin validation redirect target.
     */
    private function validationRedirectTarget(Request $request): string
    {
        $fallback = $request->getPath() ?: '/';
        $referer = $request->getHeader('Referer');

        if (
            $referer === null
            || $referer === ''
            || preg_match('/[\x00-\x1F\x7F]/', $referer)
        ) {
            return $fallback;
        }

        $parts = parse_url($referer);

        if (!is_array($parts) || !$this->hasSafeOrigin($parts)) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '/');
        $decodedPath = rawurldecode($path);

        if (
            !str_starts_with($path, '/')
            || str_starts_with($decodedPath, '//')
            || str_contains($decodedPath, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decodedPath)
        ) {
            return $fallback;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $path . $query;
    }

    /**
     * Check whether parsed URL parts use the configured application origin.
     */
    private function hasSafeOrigin(array $parts): bool
    {
        if (!isset($parts['host']) && !isset($parts['scheme'])) {
            return true;
        }

        $appParts = parse_url($this->appUrl);

        if (
            !is_array($appParts)
            || !isset($appParts['scheme'], $appParts['host'])
            || !isset($parts['scheme'], $parts['host'])
        ) {
            return false;
        }

        return strtolower((string) $parts['scheme']) === strtolower((string) $appParts['scheme'])
            && strtolower((string) $parts['host']) === strtolower((string) $appParts['host'])
            && $this->urlPort($parts) === $this->urlPort($appParts);
    }

    /**
     * Return an explicit or scheme-default URL port.
     */
    private function urlPort(array $parts): ?int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme === 'https') {
            return 443;
        }

        return $scheme === 'http' ? 80 : null;
    }
}

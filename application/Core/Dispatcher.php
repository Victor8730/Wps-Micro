<?php

declare(strict_types=1);

namespace Core;

use Exceptions\CsrfTokenMismatchException;
use Exceptions\HttpNotFoundException;
use Exceptions\MethodNotAllowedException;
use Exceptions\ValidationException;

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
     * Create a dispatcher.
     */
    public function __construct(
        Router $router,
        Container $container,
        MiddlewarePipeline $pipeline,
        ErrorHandler $errorHandler,
        array $globalMiddleware = [],
        array $routeMiddleware = [],
        array $notFoundAction = []
    ) {
        $this->router = $router;
        $this->container = $container;
        $this->pipeline = $pipeline;
        $this->errorHandler = $errorHandler;
        $this->globalMiddleware = $globalMiddleware;
        $this->routeMiddleware = $routeMiddleware;
        $this->notFoundAction = $notFoundAction;
    }

    /**
     * Dispatch the request and return a response.
     */
    public function dispatch(Request $request): Response
    {
        return $this->handleExceptions($request, function () use ($request): Response {
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
        ob_start();

        try {
            $result = $this->callAction($controller, $actionMethod, $parameters);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $output = $output === false ? '' : $output;

        if ($result instanceof Response) {
            if ($output !== '') {
                $result->setContent($output . $result->getContent());
            }

            return $result;
        }

        if (is_string($result)) {
            return new Response($output . $result);
        }

        return new Response($output);
    }

    /**
     * Call the action with named route parameters.
     */
    private function callAction(object $controller, string $actionMethod, array $parameters)
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

        try {
            $this->container->instance(Request::class, $request);
            $controller = $this->container->make($controllerClass);

            return $this->executeAction($controller, $actionMethod)
                ->setStatusCode(404);
        } catch (\Throwable $e) {
            return new Response('Page not found', 404);
        }
    }

    /**
     * Build a validation failure response.
     */
    private function validationFailed(Request $request, ValidationException $exception): Response
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('errors', $exception->errors());
        $session->flash('old_input', $this->flashableInput($request));

        if ($request->expectsJson()) {
            return new JsonResponse(['errors' => $exception->errors()], 422);
        }

        $target = $request->getHeader('Referer', $request->getPath()) ?: '/';

        return new RedirectResponse($target);
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
}

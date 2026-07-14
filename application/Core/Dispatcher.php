<?php

declare(strict_types=1);

namespace Core;

use Controllers\Controller404;
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
     * Middleware executed before route matching.
     */
    private array $globalMiddleware;

    /**
     * Middleware executed after a route is matched.
     */
    private array $routeMiddleware;

    /**
     * Create a dispatcher.
     */
    public function __construct(
        Router $router,
        Container $container,
        MiddlewarePipeline $pipeline,
        array $globalMiddleware = [],
        array $routeMiddleware = []
    ) {
        $this->router = $router;
        $this->container = $container;
        $this->pipeline = $pipeline;
        $this->globalMiddleware = $globalMiddleware;
        $this->routeMiddleware = $routeMiddleware;
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
                $controller = $this->container->make(
                    $match->getControllerClass(),
                    ['request' => $request]
                );

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
            return $this->methodNotAllowed($e);
        } catch (HttpNotFoundException $e) {
            return $this->notFound($request);
        } catch (CsrfTokenMismatchException $e) {
            return new Response('CSRF token mismatch.', 419);
        } catch (ValidationException $e) {
            return $this->validationFailed($request, $e);
        }
    }

    /**
     * Build a method not allowed response.
     */
    private function methodNotAllowed(MethodNotAllowedException $exception): Response
    {
        return new Response('Method not allowed', 405, [
            'Allow' => implode(', ', $exception->getAllowedMethods()),
        ]);
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
        try {
            $controller = $this->container->make(Controller404::class, ['request' => $request]);

            return $this->executeAction($controller, 'actionIndex')
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
        $session->flash('old_input', $request->getRequest());

        if ($request->isAjax()) {
            return new JsonResponse(['errors' => $exception->errors()], 422);
        }

        $target = $request->getHeader('Referer', $request->getPath()) ?: '/';

        return new RedirectResponse($target);
    }
}

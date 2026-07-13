<?php

declare(strict_types=1);

namespace Core;

use Controllers\Controller404;
use Exceptions\CsrfTokenMismatchException;
use Exceptions\HttpNotFoundException;

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
     * CSRF request guard.
     */
    private Csrf $csrf;

    /**
     * Create a dispatcher.
     */
    public function __construct(Router $router, Container $container, Csrf $csrf)
    {
        $this->router = $router;
        $this->container = $container;
        $this->csrf = $csrf;
    }

    /**
     * Dispatch the request and return a response.
     */
    public function dispatch(Request $request): Response
    {
        try {
            $this->guardCsrf($request);

            $match = $this->router->match($request);
            $controllerClass = $match->getControllerClass();
            $actionMethod = $match->getActionMethod();
            $controller = $this->container->make($controllerClass, ['request' => $request]);

            return $this->executeAction($controller, $actionMethod, $match->getParameters());
        } catch (HttpNotFoundException $e) {
            return $this->notFound($request);
        } catch (CsrfTokenMismatchException $e) {
            return new Response('CSRF token mismatch.', 419);
        }
    }

    /**
     * Protect unsafe HTTP methods from cross-site request forgery.
     *
     * @throws CsrfTokenMismatchException
     */
    private function guardCsrf(Request $request): void
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (!$this->csrf->validate($request)) {
            throw new CsrfTokenMismatchException();
        }
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
}

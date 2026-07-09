<?php

declare(strict_types=1);

namespace Core;

use Controllers\Controller404;
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
     * Create a dispatcher.
     */
    public function __construct(Router $router, Container $container)
    {
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * Dispatch the request and return a response.
     */
    public function dispatch(Request $request): Response
    {
        try {
            $match = $this->router->match($request);
            $controllerClass = $match->getControllerClass();
            $actionMethod = $match->getActionMethod();
            $controller = $this->container->make($controllerClass, ['request' => $request]);

            return $this->executeAction($controller, $actionMethod);
        } catch (HttpNotFoundException $e) {
            return $this->notFound($request);
        }
    }

    /**
     * Execute a controller action and normalize the result to a response.
     */
    private function executeAction(object $controller, string $actionMethod): Response
    {
        ob_start();

        try {
            $result = $controller->$actionMethod();
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

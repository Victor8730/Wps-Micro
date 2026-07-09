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
     * Create a dispatcher.
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
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
            $controller = new $controllerClass($request);

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
            return $this->executeAction(new Controller404($request), 'actionIndex')
                ->setStatusCode(404);
        } catch (\Throwable $e) {
            return new Response('Page not found', 404);
        }
    }
}

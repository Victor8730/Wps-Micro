<?php

declare(strict_types=1);

namespace Core;

use Exceptions\HttpNotFoundException;

class Router
{
    /**
     * Explicit route definitions.
     */
    private array $routes = [];

    /**
     * Default controller name.
     */
    private string $defaultController;

    /**
     * Default action name.
     */
    private string $defaultAction;

    /**
     * Configure the router.
     */
    public function __construct(Config $config)
    {
        $this->defaultController = (string) $config->get('router.default_controller', 'home');
        $this->defaultAction = (string) $config->get('router.default_action', 'index');
    }

    /**
     * Register a GET route.
     *
     * @param array|string $handler
     */
    public function get(string $path, $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param array|string $handler
     */
    public function post(string $path, $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param array|string $handler
     */
    public function put(string $path, $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route.
     *
     * @param array|string $handler
     */
    public function patch(string $path, $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param array|string $handler
     */
    public function delete(string $path, $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Register a route for one or more HTTP methods.
     *
     * @param array|string $handler
     */
    public function add($methods, string $path, $handler): self
    {
        foreach ((array) $methods as $method) {
            $this->routes[] = [
                'method' => strtoupper((string) $method),
                'path' => $this->normalizePath($path),
                'handler' => $handler,
            ];
        }

        return $this;
    }

    /**
     * Match the request to a controller action.
     *
     * @throws HttpNotFoundException
     */
    public function match(Request $request): RouteMatch
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->getMethod()) {
                continue;
            }

            $parameters = $this->matchPath($route['path'], $request->getPath());

            if ($parameters === null) {
                continue;
            }

            return $this->buildRouteMatch($route['handler'], $parameters);
        }

        return $this->matchConventionRoute($request);
    }

    /**
     * Match the request to a convention-based controller action.
     *
     * @throws HttpNotFoundException
     */
    private function matchConventionRoute(Request $request): RouteMatch
    {
        $segments = $this->getSegments($request->getPath());
        $controllerName = $segments[0] ?? $this->defaultController;
        $actionName = $segments[1] ?? $this->defaultAction;

        if (!$this->isValidRouteSegment($controllerName) || !$this->isValidRouteSegment($actionName)) {
            throw new HttpNotFoundException();
        }

        $controllerClass = Base::PATH_CONTROLLERS . '\Controller' . ucfirst($controllerName);
        $actionMethod = 'action' . ucfirst($actionName);

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $actionMethod)) {
            throw new HttpNotFoundException();
        }

        return new RouteMatch($controllerClass, $actionMethod);
    }

    /**
     * Build a route match from a registered handler.
     *
     * @param array|string $handler
     *
     * @throws HttpNotFoundException
     */
    private function buildRouteMatch($handler, array $parameters): RouteMatch
    {
        if (is_string($handler)) {
            $handlerParts = explode('@', $handler, 2);
        } elseif (is_array($handler) && count($handler) === 2) {
            $handlerParts = array_values($handler);
        } else {
            throw new HttpNotFoundException();
        }

        if (count($handlerParts) !== 2) {
            throw new HttpNotFoundException();
        }

        $controllerClass = (string) $handlerParts[0];
        $actionMethod = (string) $handlerParts[1];

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $actionMethod)) {
            throw new HttpNotFoundException();
        }

        return new RouteMatch($controllerClass, $actionMethod, $parameters);
    }

    /**
     * Match a route path pattern against the request path.
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        $parameterNames = [];
        $tokens = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $routePath,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ($tokens === false) {
            return null;
        }

        $pattern = '';

        foreach ($tokens as $token) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $token, $matches)) {
                $parameterNames[] = $matches[1];
                $pattern .= '([^/]+)';
                continue;
            }

            $pattern .= preg_quote($token, '#');
        }

        if (!preg_match('#^' . $pattern . '$#', $this->normalizePath($requestPath), $matches)) {
            return null;
        }

        array_shift($matches);
        $parameters = [];

        foreach ($parameterNames as $index => $name) {
            $parameters[$name] = rawurldecode($matches[$index]);
        }

        return $parameters;
    }

    /**
     * Normalize a route path.
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Split a path into normalized route segments.
     */
    private function getSegments(string $path): array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $path)));
    }

    /**
     * Check whether a route segment is safe to use as a class or method name.
     */
    private function isValidRouteSegment(string $segment): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $segment);
    }
}

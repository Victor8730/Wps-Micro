<?php

declare(strict_types=1);

namespace Core;

use Exceptions\HttpNotFoundException;

class Router
{
    /**
     * Default controller name.
     */
    private const DEFAULT_CONTROLLER = 'home';

    /**
     * Default action name.
     */
    private const DEFAULT_ACTION = 'index';

    /**
     * Match the request to a controller action.
     *
     * @throws HttpNotFoundException
     */
    public function match(Request $request): RouteMatch
    {
        $segments = $this->getSegments($request->getPath());
        $controllerName = $segments[0] ?? self::DEFAULT_CONTROLLER;
        $actionName = $segments[1] ?? self::DEFAULT_ACTION;

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

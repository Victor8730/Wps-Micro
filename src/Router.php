<?php

declare(strict_types=1);

namespace WpsMicro\Core;

use WpsMicro\Core\Exceptions\HttpNotFoundException;
use WpsMicro\Core\Exceptions\MethodNotAllowedException;

/**
 * @phpstan-type RouteHandler array{0: string, 1: string}|non-empty-string
 * @phpstan-type RouteData array{
 *     method: string,
 *     path: string,
 *     handler: RouteHandler,
 *     middleware: list<Middleware|string>,
 *     name: ?string,
 *     constraints: array<string, string>,
 *     pattern: ?string,
 *     parameters: list<string>
 * }
 */
class Router
{
    /**
     * Explicit route definitions with compiled path metadata.
     *
     * @var list<RouteData>
     */
    private array $routes = [];

    /**
     * Registered method/path pairs used for constant-time duplicate checks.
     *
     * @var array<string, array<string, int>>
     */
    private array $routeIndexes = [];

    /**
     * Static route indexes grouped by method and path.
     *
     * @var array<string, array<string, int>>
     */
    private array $staticRoutes = [];

    /**
     * Dynamic route indexes grouped by method.
     *
     * @var array<string, list<int>>
     */
    private array $dynamicRoutes = [];

    /**
     * Named route indexes.
     *
     * @var array<string, int>
     */
    private array $namedRoutes = [];

    /**
     * Nested route group attributes.
     *
     * @var list<array{prefix: string, name: string, middleware: list<Middleware|string>}>
     */
    private array $groups = [];

    /**
     * Register a GET route.
     *
     * @param RouteHandler $handler
     */
    public function get(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * Register a HEAD route.
     *
     * @param RouteHandler $handler
     */
    public function head(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('HEAD', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param RouteHandler $handler
     */
    public function post(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param RouteHandler $handler
     */
    public function put(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route.
     *
     * @param RouteHandler $handler
     */
    public function patch(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param RouteHandler $handler
     */
    public function delete(string $path, array|string $handler): RouteDefinition
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Register a route for one or more HTTP methods.
     *
     * @param list<string>|string $methods
     * @param RouteHandler        $handler
     */
    public function add(array|string $methods, string $path, array|string $handler): RouteDefinition
    {
        $methods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            (array) $methods,
        )));

        if ($methods === [] || in_array('', $methods, true)) {
            throw new \InvalidArgumentException('At least one HTTP method is required.');
        }

        $group = $this->currentGroup();
        $path = $this->groupPath($group['prefix'], $path);

        foreach ($methods as $method) {
            if (preg_match('/^[A-Z]+$/', $method) !== 1) {
                throw new \InvalidArgumentException('Invalid HTTP method: '.$method);
            }

            if ($this->hasRoute($method, $path)) {
                throw new \InvalidArgumentException(sprintf('Duplicate route: %s %s', $method, $path));
            }
        }

        $indexes = [];

        foreach ($methods as $method) {
            $index = count($this->routes);
            $this->routes[] = [
                'method' => $method,
                'path' => $path,
                'handler' => $handler,
                'middleware' => $group['middleware'],
                'name' => null,
                'constraints' => [],
                ...$this->compilePath($path),
            ];
            $this->indexRoute($index);
            $indexes[] = $index;
        }

        return new RouteDefinition(
            $this->routes,
            $indexes,
            function (string $change, array $changedIndexes, array $previousNames): void {
                $this->refreshRoutes($change, $changedIndexes, $previousNames);
            },
            $group['name'],
            function (string $name, array $indexes): void {
                $existing = $this->namedRoutes[$name] ?? null;

                if ($existing !== null && !in_array($existing, $indexes, true)) {
                    throw new \InvalidArgumentException('Duplicate route name: '.$name);
                }
            },
        );
    }

    /**
     * Register routes that share a prefix, name prefix, or middleware.
     *
     * @param array{prefix?: string, name?: string, middleware?: list<Middleware|string>|Middleware|string} $attributes
     * @param callable(self): void                                                                            $routes
     */
    public function group(array $attributes, callable $routes): void
    {
        $parent = $this->currentGroup();
        $prefix = (string) ($attributes['prefix'] ?? '');
        $name = (string) ($attributes['name'] ?? '');
        $middleware = $this->normalizeMiddleware($attributes['middleware'] ?? []);

        $this->groups[] = [
            'prefix' => $this->groupPath($parent['prefix'], $prefix),
            'name' => $parent['name'].$name,
            'middleware' => [...$parent['middleware'], ...$middleware],
        ];

        try {
            $routes($this);
        } finally {
            array_pop($this->groups);
        }
    }

    /**
     * Match the request to a controller action.
     *
     * @throws HttpNotFoundException
     * @throws MethodNotAllowedException
     */
    public function match(Request $request): RouteMatch
    {
        $methods = $request->getMethod() === 'HEAD' ? ['HEAD', 'GET'] : [$request->getMethod()];
        $path = $this->normalizePath($request->getPath());
        [$decodedPath, $encodedSlashes] = $this->decodePath($path);

        foreach ($methods as $method) {
            $staticIndex = $this->staticRoutes[$method][$path] ?? null;

            if ($staticIndex !== null) {
                return $this->buildRouteMatch($this->routes[$staticIndex], []);
            }

            foreach ($this->dynamicRoutes[$method] ?? [] as $index) {
                $parameters = $this->matchCompiledPath($this->routes[$index], $decodedPath, $encodedSlashes);

                if ($parameters !== null) {
                    return $this->buildRouteMatch($this->routes[$index], $parameters);
                }
            }
        }

        $allowedMethods = $this->allowedMethods($path, $request->getMethod(), $decodedPath, $encodedSlashes);

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException($allowedMethods);
        }

        throw new HttpNotFoundException();
    }

    /**
     * Generate a URL path for a named route.
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $query
     */
    public function url(string $name, array $parameters = [], array $query = []): string
    {
        $index = $this->namedRoutes[$name] ?? null;

        if ($index === null) {
            throw new \InvalidArgumentException('Named route does not exist: '.$name);
        }

        $route = $this->routes[$index];
        $path = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $matches) use ($name, $parameters, $route): string {
                $parameter = $matches[1];

                if (!array_key_exists($parameter, $parameters)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Missing parameter %s for route %s.',
                        $parameter,
                        $name,
                    ));
                }

                $value = $parameters[$parameter];

                if (!is_scalar($value) && !$value instanceof \Stringable) {
                    throw new \InvalidArgumentException('Route parameters must be scalar or stringable.');
                }

                $value = (string) $value;
                $constraint = $route['constraints'][$parameter] ?? '[^/]+';

                if (preg_match($this->constraintPattern($constraint), $value) !== 1) {
                    throw new \InvalidArgumentException(sprintf(
                        'Parameter %s does not satisfy the constraint for route %s.',
                        $parameter,
                        $name,
                    ));
                }

                return rawurlencode($value);
            },
            $route['path'],
        );

        if ($path === null) {
            throw new \RuntimeException('Unable to generate URL for route: '.$name);
        }

        $queryString = http_build_query($query);

        return $queryString === '' ? $path : $path.'?'.$queryString;
    }

    /**
     * Return registered routes for diagnostics and console tooling.
     *
     * @return list<array{
     *     method: string,
     *     path: string,
     *     handler: RouteHandler,
     *     middleware: list<Middleware|string>,
     *     name: ?string,
     *     constraints: array<string, string>
     * }>
     */
    public function getRoutes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'path' => $route['path'],
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
                'name' => $route['name'],
                'constraints' => $route['constraints'],
            ],
            $this->routes,
        );
    }

    /**
     * Return methods registered for a path other than the current method.
     *
     * @param list<int> $encodedSlashes
     *
     * @return list<string>
     */
    private function allowedMethods(string $path, string $currentMethod, string $decodedPath, array $encodedSlashes): array
    {
        $methods = [];

        foreach ($this->routes as $route) {
            if ($route['method'] === $currentMethod || !$this->routeMatchesPath($route, $path, $decodedPath, $encodedSlashes)) {
                continue;
            }

            $methods[] = $route['method'];

            if ($route['method'] === 'GET') {
                $methods[] = 'HEAD';
            }
        }

        return array_values(array_unique($methods));
    }

    /**
     * Build a route match from a registered handler.
     *
     * @param RouteData             $route
     * @param array<string, string> $parameters
     *
     * @throws HttpNotFoundException
     */
    private function buildRouteMatch(array $route, array $parameters): RouteMatch
    {
        $handler = $route['handler'];

        if (is_string($handler)) {
            $handlerParts = explode('@', $handler, 2);
        } elseif (count($handler) === 2) {
            $handlerParts = $handler;
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

        return new RouteMatch(
            $controllerClass,
            $actionMethod,
            $parameters,
            $route['middleware'],
            $route['name'],
        );
    }

    /**
     * Match a compiled dynamic route against a normalized request path.
     *
     * @param RouteData $route
     * @param list<int> $encodedSlashes
     *
     * @return null|array<string, string>
     */
    private function matchCompiledPath(array $route, string $requestPath, array $encodedSlashes): ?array
    {
        $pattern = $route['pattern'];

        if ($pattern === null || preg_match($pattern, $requestPath, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($route['parameters'] as $name) {
            [$value, $offset] = $matches[$name];
            $parameters[$name] = $value;

            foreach ($encodedSlashes as $key => $slashOffset) {
                if ($slashOffset >= $offset && $slashOffset < $offset + strlen($value)) {
                    unset($encodedSlashes[$key]);
                }
            }
        }

        // Encoded slashes may belong to parameters, never to route separators.
        return $encodedSlashes === [] ? $parameters : null;
    }

    /**
     * Decode once while retaining the byte offsets of encoded path separators.
     *
     * @return array{string, list<int>}
     */
    private function decodePath(string $path): array
    {
        if (!str_contains($path, '%')) {
            return [$path, []];
        }

        $slashes = [];
        preg_match_all('/%[0-9a-f]{2}/i', $path, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => [$encoded, $offset]) {
            if (strcasecmp($encoded, '%2f') === 0) {
                $slashes[] = $offset - 2 * $index;
            }
        }

        return [rawurldecode($path), $slashes];
    }

    /**
     * Compile route placeholders and constraints once during registration.
     *
     * @param array<string, string> $constraints
     *
     * @return array{pattern: ?string, parameters: list<string>}
     */
    private function compilePath(string $path, array $constraints = []): array
    {
        $tokens = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $path,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($tokens === false) {
            throw new \InvalidArgumentException('Unable to parse route path: '.$path);
        }

        $parameters = [];
        $pattern = '';

        foreach ($tokens as $token) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $token, $matches) !== 1) {
                $pattern .= preg_quote($token);

                continue;
            }

            $name = $matches[1];

            if (in_array($name, $parameters, true)) {
                throw new \InvalidArgumentException('Duplicate route parameter: '.$name);
            }

            $parameters[] = $name;
            $constraint = $constraints[$name] ?? '[^/]+';
            $this->assertValidConstraint($constraint);
            $pattern .= '(?P<'.$name.'>'.$constraint.')';
        }

        foreach (array_keys($constraints) as $name) {
            if (!in_array($name, $parameters, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Route parameter %s does not exist in path %s.',
                    $name,
                    $path,
                ));
            }
        }

        return [
            'pattern' => $parameters === [] ? null : $this->regexPattern('^'.$pattern.'$'),
            'parameters' => $parameters,
        ];
    }

    /**
     * Refresh only routes changed through their fluent definition.
     *
     * @param list<int>         $indexes
     * @param list<string|null> $previousNames
     */
    private function refreshRoutes(string $change, array $indexes, array $previousNames): void
    {
        if ($change === 'constraints') {
            foreach ($indexes as $index) {
                $route = $this->routes[$index];
                $this->routes[$index] = [
                    ...$route,
                    ...$this->compilePath($route['path'], $route['constraints']),
                ];
            }

            return;
        }

        foreach ($previousNames as $previousName) {
            if ($previousName !== null && in_array($this->namedRoutes[$previousName] ?? null, $indexes, true)) {
                unset($this->namedRoutes[$previousName]);
            }
        }

        foreach ($indexes as $index) {
            $name = $this->routes[$index]['name'];

            if ($name !== null && !isset($this->namedRoutes[$name])) {
                $this->namedRoutes[$name] = $index;
            }
        }
    }

    /**
     * Add one registered route to static, dynamic, and name indexes.
     */
    private function indexRoute(int $index): void
    {
        $route = $this->routes[$index];

        $this->routeIndexes[$route['method']][$route['path']] = $index;

        if ($route['pattern'] === null) {
            $this->staticRoutes[$route['method']][$route['path']] = $index;
        } else {
            $this->dynamicRoutes[$route['method']][] = $index;
        }

        if ($route['name'] === null) {
            return;
        }

        $existing = $this->namedRoutes[$route['name']] ?? null;

        if ($existing !== null && $this->routes[$existing]['path'] !== $route['path']) {
            throw new \InvalidArgumentException('Duplicate route name: '.$route['name']);
        }

        $this->namedRoutes[$route['name']] = $existing ?? $index;
    }

    /**
     * Check whether one route path matches a normalized request path.
     *
     * @param RouteData $route
     * @param list<int> $encodedSlashes
     */
    private function routeMatchesPath(array $route, string $path, string $decodedPath, array $encodedSlashes): bool
    {
        return $route['pattern'] === null
            ? $route['path'] === $path
            : $this->matchCompiledPath($route, $decodedPath, $encodedSlashes) !== null;
    }

    /**
     * Check whether an exact method and route pattern are already registered.
     */
    private function hasRoute(string $method, string $path): bool
    {
        return isset($this->routeIndexes[$method][$path]);
    }

    /**
     * Return the current merged group attributes.
     *
     * @return array{prefix: string, name: string, middleware: list<Middleware|string>}
     */
    private function currentGroup(): array
    {
        if ($this->groups === []) {
            return [
                'prefix' => '/',
                'name' => '',
                'middleware' => [],
            ];
        }

        return $this->groups[array_key_last($this->groups)];
    }

    /**
     * Combine a group prefix and route path.
     */
    private function groupPath(string $prefix, string $path): string
    {
        return $this->normalizePath(trim($prefix, '/').'/'.trim($path, '/'));
    }

    /**
     * Normalize group middleware to a list.
     *
     * @param array<array-key, Middleware|string>|Middleware|string $middleware
     *
     * @return list<Middleware|string>
     */
    private function normalizeMiddleware(array|string|Middleware $middleware): array
    {
        return is_array($middleware) ? array_values($middleware) : [$middleware];
    }

    /**
     * Validate a route constraint as a complete regular expression fragment.
     */
    private function assertValidConstraint(string $constraint): void
    {
        if ($constraint === '' || @preg_match($this->constraintPattern($constraint), '') === false) {
            throw new \InvalidArgumentException('Invalid route constraint: '.$constraint);
        }
    }

    /**
     * Wrap a route constraint for standalone validation.
     */
    private function constraintPattern(string $constraint): string
    {
        return $this->regexPattern('^(?:'.$constraint.')$');
    }

    /**
     * Wrap a regular expression with a delimiter not used by its body.
     */
    private function regexPattern(string $expression): string
    {
        foreach (['~', '#', '%', '!', '@', ';', '`', ',', ':'] as $delimiter) {
            if (!str_contains($expression, $delimiter)) {
                return $delimiter.$expression.$delimiter.'uD';
            }
        }

        throw new \InvalidArgumentException('Route constraint uses every supported regex delimiter.');
    }

    /**
     * Normalize a route path.
     */
    private function normalizePath(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

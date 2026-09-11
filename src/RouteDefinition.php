<?php

declare(strict_types=1);

namespace WpsMicro\Core;

/**
 * @phpstan-import-type RouteData from Router
 */
class RouteDefinition
{
    /**
     * Router route storage.
     *
     * @var list<RouteData>
     */
    private array $routes;

    /**
     * Route indexes covered by this definition.
     *
     * @var list<int>
     */
    private array $indexes;

    /**
     * Callback used to refresh router indexes after route metadata changes.
     *
     * @var \Closure(string, list<int>, list<string|null>): void
     */
    private \Closure $onChange;

    /**
     * Name prefix inherited from nested groups.
     */
    private string $namePrefix;

    /**
     * Create a route definition wrapper.
     *
     * @param list<RouteData> $routes
     * @param list<int>       $indexes
     * @param null|callable(string, list<int>, list<string|null>): void $onChange
     */
    public function __construct(
        array &$routes,
        array $indexes,
        ?callable $onChange = null,
        string $namePrefix = '',
    ) {
        $this->routes = &$routes;
        $this->indexes = $indexes;
        if ($onChange === null) {
            $this->onChange = static function (string $change, array $indexes, array $previousNames): void {
            };
        } else {
            $this->onChange = \Closure::fromCallable($onChange);
        }

        $this->namePrefix = $namePrefix;
    }

    /**
     * Attach route-level middleware.
     *
     * @param array<int, Middleware|string>|Middleware|string $middleware
     */
    public function middleware(array|string|Middleware $middleware): self
    {
        foreach ($this->indexes as $index) {
            foreach ((array) $middleware as $item) {
                $this->routes[$index]['middleware'][] = $item;
            }
        }

        return $this;
    }

    /**
     * Assign a unique name used for URL generation.
     */
    public function name(string $name): self
    {
        $name = $this->namePrefix.trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('Route name cannot be empty.');
        }

        foreach ($this->routes as $index => $route) {
            if (
                !in_array($index, $this->indexes, true)
                && ($route['name'] ?? null) === $name
            ) {
                throw new \InvalidArgumentException('Duplicate route name: '.$name);
            }
        }

        $previousNames = [];

        foreach ($this->indexes as $index) {
            $route = $this->routes[$index];
            $previousNames[] = $route['name'] ?? null;
            $route['name'] = $name;
            $this->routes[$index] = $route;
        }

        ($this->onChange)('name', $this->indexes, $previousNames);

        return $this;
    }

    /**
     * Constrain one or more route parameters with regular expressions.
     *
     * @param array<string, string>|string $parameter
     */
    public function where(array|string $parameter, ?string $constraint = null): self
    {
        if (is_string($parameter)) {
            if ($constraint === null) {
                throw new \InvalidArgumentException('A route constraint is required.');
            }

            $constraints = [$parameter => $constraint];
        } else {
            if ($constraint !== null) {
                throw new \InvalidArgumentException('Pass either one parameter or a constraint map.');
            }

            $constraints = $parameter;
        }

        foreach ($constraints as $name => $pattern) {
            $this->validateConstraint((string) $name, $pattern);
        }

        foreach ($this->indexes as $index) {
            foreach ($constraints as $name => $pattern) {
                $this->routes[$index]['constraints'][(string) $name] = $pattern;
            }
        }

        ($this->onChange)('constraints', $this->indexes, []);

        return $this;
    }

    /**
     * Require one or more route parameters to contain only digits.
     *
     * @param list<string>|string $parameters
     */
    public function whereNumber(array|string $parameters): self
    {
        return $this->where(array_fill_keys((array) $parameters, '[0-9]+'));
    }

    /**
     * Require one or more route parameters to contain a UUID.
     *
     * @param list<string>|string $parameters
     */
    public function whereUuid(array|string $parameters): self
    {
        return $this->where(array_fill_keys(
            (array) $parameters,
            '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}',
        ));
    }

    /**
     * Validate a constraint before mutating shared router state.
     */
    private function validateConstraint(string $name, string $pattern): void
    {
        if ($name === '' || $pattern === '') {
            throw new \InvalidArgumentException('Route parameter and constraint cannot be empty.');
        }

        foreach ($this->indexes as $index) {
            $path = (string) $this->routes[$index]['path'];

            if (preg_match('/\{'.preg_quote($name, '/').'\}/', $path) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    'Route parameter %s does not exist in path %s.',
                    $name,
                    $path,
                ));
            }
        }

        if (@preg_match($this->constraintPattern($pattern), '') === false) {
            throw new \InvalidArgumentException('Invalid route constraint: '.$pattern);
        }
    }

    /**
     * Wrap a constraint with a delimiter not used by its body.
     */
    private function constraintPattern(string $pattern): string
    {
        foreach (['~', '#', '%', '!', '@', ';', '`', ',', ':'] as $delimiter) {
            if (!str_contains($pattern, $delimiter)) {
                return $delimiter.'^(?:'.$pattern.')$'.$delimiter.'uD';
            }
        }

        throw new \InvalidArgumentException('Route constraint uses every supported regex delimiter.');
    }
}

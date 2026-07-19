<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class RouteDefinition
{
    /**
     * Router route storage.
     */
    private array $routes;

    /**
     * Route indexes covered by this definition.
     */
    private array $indexes;

    /**
     * Create a route definition wrapper.
     */
    public function __construct(array &$routes, array $indexes)
    {
        $this->routes = &$routes;
        $this->indexes = $indexes;
    }

    /**
     * Attach route-level middleware.
     *
     * @param array|string $middleware
     */
    public function middleware($middleware): self
    {
        foreach ($this->indexes as $index) {
            foreach ((array) $middleware as $item) {
                $this->routes[$index]['middleware'][] = $item;
            }
        }

        return $this;
    }
}

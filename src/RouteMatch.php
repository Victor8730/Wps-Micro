<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class RouteMatch
{
    /**
     * Controller class name.
     */
    private string $controllerClass;

    /**
     * Controller action method.
     */
    private string $actionMethod;

    /**
     * Route parameters extracted from the request path.
     *
     * @var array<string, string>
     */
    private array $parameters;

    /**
     * Middleware assigned to the matched route.
     *
     * @var list<Middleware|string>
     */
    private array $middleware;

    /**
     * Registered route name.
     */
    private ?string $name;

    /**
     * Create a route match value object.
     *
     * @param array<string, string>    $parameters
     * @param list<Middleware|string> $middleware
     */
    public function __construct(
        string $controllerClass,
        string $actionMethod,
        array $parameters = [],
        array $middleware = [],
        ?string $name = null,
    ) {
        $this->controllerClass = $controllerClass;
        $this->actionMethod = $actionMethod;
        $this->parameters = $parameters;
        $this->middleware = $middleware;
        $this->name = $name;
    }

    /**
     * Return the matched controller class.
     */
    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    /**
     * Return the matched action method.
     */
    public function getActionMethod(): string
    {
        return $this->actionMethod;
    }

    /**
     * Return route parameters.
     *
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Return route middleware.
     *
     * @return list<Middleware|string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Return the registered route name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}

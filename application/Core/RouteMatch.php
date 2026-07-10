<?php

declare(strict_types=1);

namespace Core;

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
     */
    private array $parameters;

    /**
     * Create a route match value object.
     */
    public function __construct(string $controllerClass, string $actionMethod, array $parameters = [])
    {
        $this->controllerClass = $controllerClass;
        $this->actionMethod = $actionMethod;
        $this->parameters = $parameters;
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
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
